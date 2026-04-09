<?php

namespace App\Form;

use App\Entity\ContratEmbauche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContratEmbaucheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'required' => false,
                'empty_data' => '',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Alternance' => 'Alternance',
                    'Freelance' => 'Freelance',
                ],
                'placeholder' => 'Choisir un type',
                'constraints' => [
                    new NotBlank(['message' => 'Le type de contrat est obligatoire.']),
                    new Choice(['choices' => ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance']]),
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de debut',
                'required' => false,
                'constraints' => [new NotBlank(['message' => 'La date de debut est obligatoire.'])],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'required' => false,
                'constraints' => [new NotBlank(['message' => 'La date de fin est obligatoire.'])],
            ])
            ->add('salaire', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Salaire',
                'required' => false,
                'empty_data' => '0',
                'constraints' => [
                    new NotBlank(['message' => 'Le salaire est obligatoire.']),
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('volumeHoraire', TextType::class, [
                'label' => 'Volume horaire',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Le volume horaire est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
            ])
            ->add('avantages', ChoiceType::class, [
                'label' => 'Avantages',
                'required' => false,
                'choices' => [
                    'Tickets restaurants' => 'Tickets restaurants',
                    'Assurance Maladie' => 'Assurance Maladie',
                    'Transport' => 'Transport',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('idRecrutement', ChoiceType::class, [
                'label' => 'ID Recrutement',
                'required' => false,
                'choices' => $options['recrutement_choices'],
                'placeholder' => 'Choisir un recrutement accepte',
                'constraints' => [new NotBlank(['message' => 'Le recrutement est obligatoire.'])],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn-save'],
            ]);

        $builder->get('avantages')->addModelTransformer(new CallbackTransformer(
            static fn ($value) => $value ? explode(', ', $value) : [],
            static fn ($value) => $value ? implode(', ', $value) : null
        ));

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $form->getData();

            if ($data->getDateDebut() && $data->getDateFin() && $data->getDateDebut() >= $data->getDateFin()) {
                $form->get('dateFin')->addError(new FormError('La date de fin doit etre apres la date de debut.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContratEmbauche::class,
            'recrutement_choices' => [],
        ]);
    }
}
