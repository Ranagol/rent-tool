<?php

namespace App\Form;

use App\Entity\Rental;
use App\Entity\RentalItem;
use App\Entity\Tool;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Lets the salesperson mark returned tools that need repair; all others become available. */
class RentalReturnType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Rental $rental */
        $rental = $options['rental'];
        $toolIds = $rental->getItems()->map(static fn (RentalItem $item): ?int => $item->getTool()->getId())->toArray();

        $builder->add('repairTools', EntityType::class, [
            'label' => 'Tools that need repair',
            'class' => Tool::class,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choice_label' => static fn (Tool $tool): string => sprintf('%s (%s)', $tool->getName(), $tool->getSerialNumber()),
            'query_builder' => static fn ($repository) => $repository->createQueryBuilder('t')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', $toolIds)
                ->orderBy('t.name'),
            'help' => 'Checked tools are set to "In repair". All other tools become available.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('rental');
        $resolver->setAllowedTypes('rental', Rental::class);
    }
}
