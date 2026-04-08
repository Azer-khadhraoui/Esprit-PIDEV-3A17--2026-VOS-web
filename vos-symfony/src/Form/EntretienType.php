<?php

namespace App\Form;

use App\Entity\Entretien;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateEntretien', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Date de l\'entretien',
            ])
            ->add('heureEntretien', TimeType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Heure',
            ])
            ->add('typeEntretien', ChoiceType::class, [
                'choices' => ['RH' => 'RH', 'TECHNIQUE' => 'TECHNIQUE'],
                'required' => true,
                'placeholder' => '-- Choisir --',
                'label' => 'Type d\'entretien',
            ])
            ->add('statutEntretien', ChoiceType::class, [
                'choices' => [
                    'Planifié' => 'Planifié',
                    'Terminé' => 'Terminé',
                    'Annulé' => 'Annulé',
                    'En attente' => 'En attente',
                ],
                'required' => true,
                'placeholder' => '-- Choisir --',
                'label' => 'Statut',
            ])
            ->add('lieu', TextType::class, [
                'required' => true,
                'label' => 'Lieu',
            ])
            ->add('typeTest', TextType::class, [
                'required' => true,
                'label' => 'Type de test',
            ])
            ->add('lienReunion', UrlType::class, [
                'required' => false,
                'label' => 'Lien de réunion',
                'default_protocol' => 'https',
            ])
            ->add('idCandidature', IntegerType::class, [
                'required' => false,
                'label' => 'ID Candidature',
            ])
            ->add('idUtilisateur', IntegerType::class, [
                'required' => false,
                'label' => 'ID Utilisateur',
            ])
            ->add('questionsEntretien', TextareaType::class, [
                'required' => false,
                'label' => 'Questions',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Entretien::class]);
    }
}
