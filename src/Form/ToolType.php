<?php

namespace App\Form;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ToolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tool = $options['data'] ?? null;
        $isRented = $tool instanceof Tool && ToolStatus::Rented === $tool->getStatus();

        $builder
            ->add('name', TextType::class, ['label' => 'Name'])
            ->add('serialNumber', TextType::class, ['label' => 'Serial number'])
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image URL',
                'default_protocol' => null,
                'attr' => ['placeholder' => 'https://…'],
            ])
            ->add('dailyRentCost', NumberType::class, [
                'label' => 'Daily rent cost (€)',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01', 'inputmode' => 'decimal'],
            ])
            ->add('status', EnumType::class, [
                'label' => 'Status',
                'class' => ToolStatus::class,
                'choices' => $isRented ? [ToolStatus::Rented] : ToolStatus::manual(),
                'choice_label' => static fn (ToolStatus $status): string => $status->label(),
                'disabled' => $isRented,
                'help' => $isRented ? 'The status of a rented tool is managed by the rental workflow.' : null,
            ])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('comments', TextareaType::class, ['label' => 'Comments', 'required' => false, 'attr' => ['rows' => 3]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tool::class]);
    }
}
