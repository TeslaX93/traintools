<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

class SimpleDistanceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('station1', TextType::class, ['attr' => ['class' => 'autocompleteStation']])
			->add('station2', TextType::class, ['attr' => ['class' => 'autocompleteStation']])
			->add('station3', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('station4', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('station5', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('station6', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('station7', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('station8', TextType::class, ['required' => false, 'attr' => ['class' => 'autocompleteStation']])
			->add('submitBtn', SubmitType::class, ['label' => 'Dalej'])
			->add('clearBtn', ResetType::class, ['label' => 'Wyczyść'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
