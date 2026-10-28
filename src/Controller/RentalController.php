<?php

namespace App\Controller;

use App\Entity\Rental;
use App\Entity\RentalStatus;
use App\Entity\Tool;
use App\Entity\User;
use App\Exception\RentalException;
use App\Form\RentalReturnType;
use App\Form\RentalType;
use App\Repository\RentalRepository;
use App\Service\RentalManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Rentals and their items are never deleted (README 5.5.4); items can only be set when the rental is created.
#[Route('/rentals')]
final class RentalController extends AbstractController
{
    private const PER_PAGE = 10;
    // Query-string sort key => DQL expression
    private const SORTABLE_FIELDS = [
        'id' => 'r.id',
        'customer' => 'c.name',
        'start' => 'r.rentalStartDate',
        'expected' => 'r.expectedReturnDate',
        'total' => 'r.totalRentCost',
    ];

    public function __construct(private readonly RentalManager $rentalManager)
    {
    }

    #[Route(name: 'app_rental_index', methods: ['GET'])]
    public function index(Request $request, RentalRepository $rentalRepository): Response
    {
        $term = trim($request->query->getString('q'));
        $status = RentalStatus::tryFrom($request->query->getString('status'));
        $sort = $request->query->getString('sort', 'id');
        $sort = isset(self::SORTABLE_FIELDS[$sort]) ? $sort : 'id';
        $direction = 'asc' === $request->query->getString('dir') ? 'asc' : 'desc';
        $page = max(1, $request->query->getInt('page', 1));

        $result = $rentalRepository->search($term, $status, self::SORTABLE_FIELDS[$sort], $direction, $page, self::PER_PAGE);

        return $this->render('rental/index.html.twig', [
            'rentals' => $result['items'],
            'total' => $result['total'],
            'term' => $term,
            'status' => $status,
            'statuses' => RentalStatus::cases(),
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'app_rental_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ClockInterface $clock): Response
    {
        $now = $clock->now();
        $start = $now->setTime((int) $now->format('H'), (int) $now->format('i'));
        $rental = (new Rental())
            ->setUser($this->getUser() instanceof User ? $this->getUser() : null)
            ->setRentalStartDate($start)
            ->setExpectedReturnDate($start->modify('+1 day'));

        $form = $this->createForm(RentalType::class, $rental, ['include_tools' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<Tool> $tools */
            $tools = $form->get('tools')->getData()->toArray();

            try {
                $this->rentalManager->create($rental, $tools);
                $this->addFlash('success', sprintf('Rental #%d was created.', $rental->getId()));

                return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
            } catch (RentalException $e) {
                $form->get('tools')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('rental/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_rental_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Rental $rental): Response
    {
        return $this->render('rental/show.html.twig', ['rental' => $rental]);
    }

    #[Route('/{id}/edit', name: 'app_rental_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Rental $rental): Response
    {
        if (!$rental->isActive()) {
            $this->addFlash('error', 'Only active rentals can be edited.');

            return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(RentalType::class, $rental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->rentalManager->update($rental);
            $this->addFlash('success', sprintf('Rental #%d was updated.', $rental->getId()));

            return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rental/edit.html.twig', ['rental' => $rental, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/return', name: 'app_rental_return', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function return(Request $request, Rental $rental): Response
    {
        if (!$rental->isActive()) {
            $this->addFlash('error', 'Only active rentals can be returned.');

            return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(RentalReturnType::class, null, ['rental' => $rental]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->rentalManager->return($rental, $form->get('repairTools')->getData()->toArray());
                $this->addFlash('success', sprintf('Rental #%d was returned.', $rental->getId()));

                return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
            } catch (RentalException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('rental/return.html.twig', ['rental' => $rental, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/cancel', name: 'app_rental_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, Rental $rental): Response
    {
        if (!$this->isCsrfTokenValid('cancel'.$rental->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token. The rental was not cancelled.');
        } else {
            try {
                $this->rentalManager->cancel($rental);
                $this->addFlash('success', sprintf('Rental #%d was cancelled.', $rental->getId()));
            } catch (RentalException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()], Response::HTTP_SEE_OTHER);
    }

    private function formResponse(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
