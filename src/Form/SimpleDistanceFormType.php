<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class SimpleDistanceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('station1', TextType::class)
			->add('station2', TextType::class)
			->add('station3', TextType::class, ['required' => false])
			->add('station4', TextType::class, ['required' => false])
			->add('station5', TextType::class, ['required' => false])
			->add('station6', TextType::class, ['required' => false])
			->add('station7', TextType::class, ['required' => false])
			->add('station8', TextType::class, ['required' => false])
			->add('submitBtn', SubmitType::class, ['label' => 'Dalej'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
