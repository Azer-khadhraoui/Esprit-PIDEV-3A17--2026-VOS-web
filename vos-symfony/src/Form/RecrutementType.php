<?php

namespace App\Form;

use App\Entity\Recrutement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class RecrutementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDecision', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de decision',
                'required' => false,
                'attr' => [
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
                'constraints' => [new NotBlank(['message' => 'La date de decision est obligatoire.'])],
            ])
            ->add('decisionFinale', ChoiceType::class, [
                'label' => 'Decision finale',
                'required' => false,
                'choices' => [
                    'En attente' => 'En attente',
                    'Accepte' => 'Accepté',
                    'Refuse' => 'Refusé',
                ],
                'placeholder' => 'Choisir une decision',
                'constraints' => [
                    new NotBlank(['message' => 'La decision finale est obligatoire.']),
                    new Choice(['choices' => ['En attente', 'Accepté', 'Refusé']]),
                ],
            ])
            ->add('idEntretien', ChoiceType::class, [
                'label' => 'ID Entretien',
                'required' => false,
                'choices' => $options['entretien_choices'],
                'placeholder' => 'Choisir un entretien termine',
                'constraints' => [new NotBlank(['message' => 'L entretien est obligatoire.'])],
            ])
            ->add('idUtilisateur', ChoiceType::class, [
                'label' => 'ID Utilisateur',
                'required' => false,
                'choices' => $options['utilisateur_choices'],
                'placeholder' => 'Choisir un utilisateur',
                'constraints' => [new NotBlank(['message' => 'L utilisateur est obligatoire.'])],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn-save'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recrutement::class,
            'utilisateur_choices' => [],
            'entretien_choices' => [],
        ]);

        $resolver->setAllowedTypes('utilisateur_choices', 'array');
        $resolver->setAllowedTypes('entretien_choices', 'array');
    }
}
