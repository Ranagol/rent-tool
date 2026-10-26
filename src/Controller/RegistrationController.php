<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
                $entityManager->persist($user);
                $entityManager->flush();

                return $this->redirectToRoute('app_login');
            } catch (DBALException $e) {
                // Handle unique constraint violations
                if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'Unique') !== false) {
                    $form->addError(new \Symfony\Component\Form\FormError('Email is already in use'));
                    $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;

                    return $this->render('registration/index.html.twig', [
                        'form' => $form,
                    ], new Response(null, $statusCode));
                }

                throw $e;
            }
        }

        $statusCode = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('registration/index.html.twig', [
            'form' => $form,
        ], new Response(null, $statusCode));
    }
}



