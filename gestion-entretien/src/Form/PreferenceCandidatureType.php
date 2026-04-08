<?php

namespace App\Form;

use App\Entity\PreferenceCandidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreferenceCandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Type de poste souhaité - Champ texte
            ->add('type_poste_souhaite', TextType::class, [
                'label' => 'Type de poste souhaité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Développeur, Designer, Manager...',
                    'class' => 'form-control'
                ]
            ])
            
            // Mode de travail - Liste déroulante
            ->add('mode_travail', ChoiceType::class, [
                'label' => 'Mode de travail',
                'required' => false,
                'placeholder' => '-- Sélectionnez un mode de travail --',
                'choices' => [
                    '100% Présentiel' => '100% Présentiel',
                    '100% Télétravail' => '100% Télétravail',
                    'Hybride' => 'Hybride',
                ],
                'attr' => ['class' => 'form-control']
            ])
            
            // Disponibilité - Liste déroulante
            ->add('disponibilite', ChoiceType::class, [
                'label' => 'Disponibilité',
                'required' => false,
                'placeholder' => '-- Sélectionnez votre disponibilité --',
                'choices' => [
                    'Immédiatement' => 'Immédiatement',
                    'Dans 1 mois' => 'Dans 1 mois',
                    'Dans 3 mois' => 'Dans 3 mois',
                    'Dans 6 mois' => 'Dans 6 mois',
                ],
                'attr' => ['class' => 'form-control']
            ])
            
            // Mobilité géographique - Liste déroulante
            ->add('mobilite_geographique', ChoiceType::class, [
                'label' => 'Mobilité géographique',
                'required' => false,
                'placeholder' => '-- Sélectionnez votre mobilité --',
                'choices' => [
                    'Oui, national' => 'Oui, national',
                    'Oui, région' => 'Oui, région',
                    'Non' => 'Non',
                ],
                'attr' => ['class' => 'form-control']
            ])
            
            // Prêt au déplacement - Liste déroulante
            ->add('pret_deplacement', ChoiceType::class, [
                'label' => 'Prêt au déplacement',
                'required' => false,
                'placeholder' => '-- Sélectionnez votre disponibilité --',
                'choices' => [
                    'Jamais' => 'Jamais',
                    'Occasionnel' => 'Occasionnel',
                    'Fréquent' => 'Fréquent',
                ],
                'attr' => ['class' => 'form-control']
            ])
            
            // Type de contrat souhaité - Liste déroulante
            ->add('type_contrat_souhaite', ChoiceType::class, [
                'label' => 'Type de contrat souhaité',
                'required' => false,
                'placeholder' => '-- Sélectionnez un type de contrat --',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Alternance' => 'Alternance',
                    'Freelance' => 'Freelance',
                ],
                'attr' => ['class' => 'form-control']
            ])
            
            // Prétention salariale - Champ numérique
            ->add('pretention_salariale', MoneyType::class, [
                'label' => 'Prétention salariale (TND)',
                'required' => false,
                'currency' => 'TND',
                'divisor' => 1,
                'attr' => [
                    'placeholder' => '0',
                    'class' => 'form-control'
                ],
                'help' => 'Entrez le salaire annuel souhaité'
            ])
            
            // Date de disponibilité - Calendrier
            ->add('date_disponibilite', DateType::class, [
                'label' => 'Date de disponibilité',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'format' => 'yyyy-MM-dd',
                'attr' => [
                    'type' => 'date',
                    'class' => 'form-control'
                ],
                'help' => 'La date doit être dans le futur'
            ])
        ;
        // Remarque: id_utilisateur n'est pas inclus car il est défini automatiquement par le controller
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreferenceCandidature::class,
        ]);
    }
}
