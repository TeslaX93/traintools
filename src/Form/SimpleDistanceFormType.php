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
            ->add('station1', TextType::class, ['label'=> 'Stacja początkowa: ','attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station2', TextType::class, ['label'=> 'Stacja pośrednia(1): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station3', TextType::class, ['label'=> 'Stacja pośrednia(2): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station4', TextType::class, ['label'=> 'Stacja pośrednia(3): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station5', TextType::class, ['label'=> 'Stacja pośrednia(4): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station6', TextType::class, ['label'=> 'Stacja pośrednia(5): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station7', TextType::class, ['label'=> 'Stacja pośrednia(6): ','required' => false, 'attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
            ->add('station8', TextType::class, ['label'=> 'Stacja docelowa: ','attr' => ['class' => 'autocompleteStation', 'list' => 'stationsDatalist']])
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
