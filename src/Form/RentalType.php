<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Rental;
use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Repository\ToolRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class RentalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'label' => 'Customer',
                'class' => Customer::class,
                'choice_label' => static fn (Customer $customer): string => $customer->getName().' ('.$customer->getEmail().')',
                'placeholder' => 'Select a customer…',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('c')->orderBy('c.name'),
            ])
            ->add('rentalStartDate', DateTimeType::class, [
                'label' => 'Rental start',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('expectedReturnDate', DateTimeType::class, [
                'label' => 'Expected return',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;

        if ($options['include_tools']) {
            $builder->add('tools', EntityType::class, [
                'label' => 'Tools',
                'class' => Tool::class,
                'mapped' => false,
                'multiple' => true,
                'expanded' => true,
                'choice_label' => static fn (Tool $tool): string => sprintf('%s (%s) — %s €/day', $tool->getName(), $tool->getSerialNumber(), $tool->getDailyRentCost()),
                'query_builder' => static fn (ToolRepository $repository) => $repository->createQueryBuilder('t')
                    ->where('t.status = :status')
                    ->setParameter('status', ToolStatus::Available)
                    ->orderBy('t.name'),
                'constraints' => [new Count(min: 1, minMessage: 'Select at least one tool.')],
                'help' => 'Only available tools can be rented.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Rental::class, 'include_tools' => false]);
        $resolver->setAllowedTypes('include_tools', 'bool');
    }
}
