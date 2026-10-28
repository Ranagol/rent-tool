<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users')]
final class UserController extends AbstractController
{
    private const PER_PAGE = 10;
    private const SORTABLE_FIELDS = ['id', 'email'];

    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $term = trim($request->query->getString('q'));
        $sort = $request->query->getString('sort', 'id');
        $sort = \in_array($sort, self::SORTABLE_FIELDS, true) ? $sort : 'id';
        $direction = 'desc' === $request->query->getString('dir') ? 'desc' : 'asc';
        $page = max(1, $request->query->getInt('page', 1));

        $result = $userRepository->search($term, $sort, $direction, $page, self::PER_PAGE);

        return $this->render('user/index.html.twig', [
            'users' => $result['items'],
            'total' => $result['total'],
            'term' => $term,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPlainPassword($form, $user, $passwordHasher);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('User %s was created.', $user->getEmail()));

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPlainPassword($form, $user, $passwordHasher);
            $entityManager->flush();

            $this->addFlash('success', sprintf('User %s was updated.', $user->getEmail()));

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', ['user' => $user, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}', name: 'app_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token. The user was not deleted.');
        } elseif ($user->getUserIdentifier() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'You cannot delete your own account.');
        } else {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('User %s was deleted.', $user->getEmail()));
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    private function applyPlainPassword(FormInterface $form, User $user, UserPasswordHasherInterface $passwordHasher): void
    {
        $plainPassword = $form->get('plainPassword')->getData();

        if (null !== $plainPassword && '' !== $plainPassword) {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }
    }

    private function formResponse(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
