<?php

namespace App\Form;

use App\Entity\Recrutement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecrutementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDecision', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de décision',
                'required' => false,
                'attr' => [
                    'placeholder' => 'YYYY-MM-DD',
                ],
            ])
            ->add('decisionFinale', ChoiceType::class, [
                'label' => 'Décision finale',
                'required' => false,
                'choices' => [
                    'En attente' => 'En attente',
                    'Accepté' => 'Accepté',
                    'Refusé' => 'Refusé',
                ],
                'placeholder' => 'Choisir une décision',
            ])
            ->add('idEntretien', IntegerType::class, [
                'label' => 'ID Entretien',
                'required' => false,
                'attr' => ['placeholder' => 'Identifiant de l’entretien'],
            ])
            ->add('idUtilisateur', IntegerType::class, [
                'label' => 'ID Utilisateur',
                'required' => false,
                'attr' => ['placeholder' => 'Identifiant de l’utilisateur'],
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
        ]);
    }
}
