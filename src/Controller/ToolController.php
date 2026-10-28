<?php

namespace App\Controller;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Form\ToolType;
use App\Repository\ToolRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tools')]
final class ToolController extends AbstractController
{
    private const PER_PAGE = 10;
    // Query-string sort key => entity property
    private const SORTABLE_FIELDS = [
        'id' => 'id',
        'name' => 'name',
        'serial' => 'serialNumber',
        'cost' => 'dailyRentCost',
        'status' => 'status',
    ];

    #[Route(name: 'app_tool_index', methods: ['GET'])]
    public function index(Request $request, ToolRepository $toolRepository): Response
    {
        $term = trim($request->query->getString('q'));
        $status = ToolStatus::tryFrom($request->query->getString('status'));
        $sort = $request->query->getString('sort', 'id');
        $sort = isset(self::SORTABLE_FIELDS[$sort]) ? $sort : 'id';
        $direction = 'desc' === $request->query->getString('dir') ? 'desc' : 'asc';
        $page = max(1, $request->query->getInt('page', 1));

        $result = $toolRepository->search($term, $status, self::SORTABLE_FIELDS[$sort], $direction, $page, self::PER_PAGE);

        return $this->render('tool/index.html.twig', [
            'tools' => $result['items'],
            'total' => $result['total'],
            'term' => $term,
            'status' => $status,
            'statuses' => ToolStatus::cases(),
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'app_tool_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tool = new Tool();
        $form = $this->createForm(ToolType::class, $tool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tool);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Tool %s was created.', $tool->getName()));

            return $this->redirectToRoute('app_tool_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tool/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_tool_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Tool $tool): Response
    {
        return $this->render('tool/show.html.twig', ['tool' => $tool]);
    }

    #[Route('/{id}/edit', name: 'app_tool_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Tool $tool, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ToolType::class, $tool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Tool %s was updated.', $tool->getName()));

            return $this->redirectToRoute('app_tool_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tool/edit.html.twig', ['tool' => $tool, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_tool_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Tool $tool, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$tool->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token. The tool was not deleted.');

            return $this->redirectToRoute('app_tool_index', [], Response::HTTP_SEE_OTHER);
        }

        $name = $tool->getName();

        try {
            $entityManager->remove($tool);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Tool %s was deleted.', $name));
        } catch (ForeignKeyConstraintViolationException) {
            // The RentalItem.tool_id foreign key (RESTRICT) protects rental history.
            $this->addFlash('error', 'This tool cannot be deleted because it has rental history.');
        }

        return $this->redirectToRoute('app_tool_index', [], Response::HTTP_SEE_OTHER);
    }

    private function formResponse(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
