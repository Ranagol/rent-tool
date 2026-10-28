<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $isNew,
                'first_options' => [
                    'label' => 'Password',
                    'help' => $isNew ? null : 'Leave empty to keep the current password.',
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => $isNew ? [new NotBlank()] : [],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Passwords must match',
                'constraints' => [new Length(min: 8)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
