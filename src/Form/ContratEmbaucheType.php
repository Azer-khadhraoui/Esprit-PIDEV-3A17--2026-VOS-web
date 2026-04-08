<?php

namespace App\Form;

use App\Entity\ContratEmbauche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContratEmbaucheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'required' => false,
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Alternance' => 'Alternance',
                    'Freelance' => 'Freelance',
                ],
                'placeholder' => 'Choisir un type',
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'required' => false,
                'attr' => ['placeholder' => 'YYYY-MM-DD'],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'required' => false,
                'attr' => ['placeholder' => 'YYYY-MM-DD'],
            ])
            ->add('salaire', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Salaire',
                'required' => false,
            ])
            ->add('volumeHoraire', TextType::class, [
                'label' => 'Volume horaire',
                'required' => false,
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
                'placeholder' => 'Choisir un recrutement accepté',
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn-save'],
            ]);

        $builder->get('avantages')->addModelTransformer(new CallbackTransformer(
            function ($value) {
                return $value ? explode(', ', $value) : [];
            },
            function ($value) {
                return $value ? implode(', ', $value) : null;
            }
        ));


        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            if ($data->getDateDebut() && $data->getDateFin()) {
                if ($data->getDateDebut() >= $data->getDateFin()) {
                    $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
                }
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

