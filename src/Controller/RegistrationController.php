<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            try {

                // Succesfull registration
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Your account has been created successfully. Please log in.');

                return $this->redirectToRoute('app_login');
            } catch (UniqueConstraintViolationException) {
                
                // A concurrent request registered the same email after UniqueEntity validation passed.
                $form->addError(new FormError('Email is already in use'));

                return $this->render('registration/index.html.twig', [
                    'form' => $form,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        $statusCode = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('registration/index.html.twig', [
            'form' => $form,
        ], new Response(null, $statusCode));
    }
}
