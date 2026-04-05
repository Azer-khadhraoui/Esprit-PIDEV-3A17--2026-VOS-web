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
use Symfony\Component\Validator\Constraints as Assert;

class EntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateEntretien', DateType::class, [
                'widget'      => 'single_text',
                'required'    => true,
                'label'       => 'Date de l\'entretien',
                'constraints' => [
                    new Assert\NotBlank(message: 'La date est obligatoire.'),
                    new Assert\GreaterThanOrEqual([
                        'value'   => 'today',
                        'message' => 'La date doit être aujourd\'hui ou dans le futur.',
                    ]),
                ],
            ])
            ->add('heureEntretien', TimeType::class, [
                'widget'      => 'single_text',
                'required'    => true,
                'label'       => 'Heure',
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'heure est obligatoire.'),
                ],
            ])
            ->add('typeEntretien', ChoiceType::class, [
                'choices'     => ['RH' => 'RH', 'TECHNIQUE' => 'TECHNIQUE'],
                'required'    => true,
                'placeholder' => '-- Choisir --',
                'label'       => 'Type d\'entretien',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type est obligatoire.'),
                    new Assert\Choice(choices: ['RH', 'TECHNIQUE'], message: 'Type invalide.'),
                ],
            ])
            ->add('statutEntretien', ChoiceType::class, [
                'choices'     => [
                    'Planifié'   => 'Planifié',
                    'Terminé'    => 'Terminé',
                    'Annulé'     => 'Annulé',
                    'En attente' => 'En attente',
                ],
                'required'    => true,
                'placeholder' => '-- Choisir --',
                'label'       => 'Statut',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le statut est obligatoire.'),
                ],
            ])
            ->add('lieu', TextType::class, [
                'required'    => true,
                'label'       => 'Lieu',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le lieu est obligatoire.'),
                    new Assert\Length([
                        'min'        => 2,
                        'max'        => 100,
                        'minMessage' => 'Le lieu doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le lieu ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('typeTest', TextType::class, [
                'required'    => true,
                'label'       => 'Type de test',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de test est obligatoire.'),
                    new Assert\Length([
                        'min'        => 2,
                        'max'        => 100,
                        'minMessage' => 'Minimum {{ limit }} caractères.',
                        'maxMessage' => 'Maximum {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('lienReunion', UrlType::class, [
                'required'         => false,
                'label'            => 'Lien de réunion',
                'default_protocol' => 'https',
                'constraints'      => [
                    new Assert\Url(message: 'Veuillez entrer une URL valide (ex: https://...).'),
                ],
            ])
            ->add('idCandidature', IntegerType::class, [
                'required'    => false,
                'label'       => 'ID Candidature',
                'constraints' => [
                    new Assert\Positive(message: 'L\'ID candidature doit être un nombre positif.'),
                ],
            ])
            ->add('questionsEntretien', TextareaType::class, [
                'required'    => false,
                'label'       => 'Questions',
                'constraints' => [
                    new Assert\Length([
                        'max'        => 5000,
                        'maxMessage' => 'Les questions ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Entretien::class]);
    }
}
