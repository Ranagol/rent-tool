<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customers')]
final class CustomerController extends AbstractController
{
    private const PER_PAGE = 10;
    // Query-string sort key => entity property
    private const SORTABLE_FIELDS = [
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'city' => 'city',
    ];

    #[Route(name: 'app_customer_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository): Response
    {
        $term = trim($request->query->getString('q'));
        $sort = $request->query->getString('sort', 'id');
        $sort = isset(self::SORTABLE_FIELDS[$sort]) ? $sort : 'id';
        $direction = 'desc' === $request->query->getString('dir') ? 'desc' : 'asc';
        $page = max(1, $request->query->getInt('page', 1));

        $result = $customerRepository->search($term, self::SORTABLE_FIELDS[$sort], $direction, $page, self::PER_PAGE);

        return $this->render('customer/index.html.twig', [
            'customers' => $result['items'],
            'total' => $result['total'],
            'term' => $term,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Customer %s was created.', $customer->getName()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_customer_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', ['customer' => $customer]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Customer %s was updated.', $customer->getName()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', ['customer' => $customer, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_customer_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$customer->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token. The customer was not deleted.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        $name = $customer->getName();

        try {
            $entityManager->remove($customer);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Customer %s was deleted.', $name));
        } catch (ForeignKeyConstraintViolationException) {
            // The Rental.customer_id foreign key (RESTRICT) protects rental history.
            $this->addFlash('error', 'This customer cannot be deleted because they have rental history.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }

    private function formResponse(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
