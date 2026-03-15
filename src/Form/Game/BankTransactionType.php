<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Form\Game;

use FrankProjects\UltimateWarfare\Form\DTO\BankTransactionFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/** @extends AbstractType<null> */
class BankTransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraint = new PositiveOrZero();

        $builder
            ->add('cash', IntegerType::class, [
                'required' => false,
                'constraints' => [$constraint],
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('wood', IntegerType::class, [
                'required' => false,
                'constraints' => [$constraint],
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('steel', IntegerType::class, [
                'required' => false,
                'constraints' => [$constraint],
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('food', IntegerType::class, [
                'required' => false,
                'constraints' => [$constraint],
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('submit', SubmitType::class, [
                'label' => $options['submit_label'],
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BankTransactionFormDTO::class,
            'submit_label' => 'Submit',
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
