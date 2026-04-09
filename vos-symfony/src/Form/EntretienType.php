<?php

namespace App\Form;

use App\Entity\Candidature;
use App\Entity\Entretien;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EntretienType extends AbstractType
{
    public function __construct(
        private CandidatureRepository $candidatureRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $candidatureChoices = [];
        foreach ($this->candidatureRepository->createQueryBuilder('c')->orderBy('c.id_candidature', 'DESC')->getQuery()->getResult() as $candidature) {
            if (!$candidature instanceof Candidature) {
                continue;
            }

            $label = sprintf('#%d - %s', $candidature->getIdCandidature(), $candidature->getStatut() ?? 'Sans statut');
            $candidatureChoices[$label] = $candidature->getIdCandidature();
        }

        $utilisateurChoices = [];
        foreach ($this->userRepository->createQueryBuilder('u')->orderBy('u.prenom', 'ASC')->getQuery()->getResult() as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $label = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));
            $label = trim($label) !== '' ? $label : 'Utilisateur';
            $utilisateurChoices[sprintf('%s (%s)', $label, $user->getEmail() ?? 'email inconnu')] = $user->getId();
        }

        $builder
            ->add('dateEntretien', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date de l\'entretien',
                'attr' => [
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de l entretien est obligatoire.']),
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de l entretien ne peut pas etre dans le passe.',
                    ]),
                ],
            ])
            ->add('heureEntretien', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Heure',
                'constraints' => [
                    new NotBlank(['message' => 'L heure est obligatoire.']),
                ],
            ])
            ->add('typeEntretien', ChoiceType::class, [
                'choices' => ['RH' => 'RH', 'TECHNIQUE' => 'TECHNIQUE'],
                'required' => false,
                'placeholder' => '-- Choisir --',
                'label' => 'Type d\'entretien',
                'constraints' => [
                    new NotBlank(['message' => 'Le type d entretien est obligatoire.']),
                    new Choice(['choices' => ['RH', 'TECHNIQUE']]),
                ],
            ])
            ->add('statutEntretien', ChoiceType::class, [
                'choices' => [
                    'Planifié' => 'Planifié',
                    'Terminé' => 'Terminé',
                    'Annulé' => 'Annulé',
                    'En attente' => 'En attente',
                ],
                'required' => false,
                'placeholder' => '-- Choisir --',
                'label' => 'Statut',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut est obligatoire.']),
                    new Choice(['choices' => ['Planifié', 'Terminé', 'Annulé', 'En attente']]),
                ],
            ])
            ->add('lieu', TextType::class, [
                'required' => false,
                'label' => 'Lieu',
                'constraints' => [
                    new NotBlank(['message' => 'Le lieu est obligatoire.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('typeTest', TextType::class, [
                'required' => false,
                'label' => 'Type de test',
                'constraints' => [
                    new NotBlank(['message' => 'Le type de test est obligatoire.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('lienReunion', UrlType::class, [
                'required' => false,
                'label' => 'Lien de réunion',
                'default_protocol' => 'https',
            ])
            ->add('idCandidature', ChoiceType::class, [
                'choices' => $candidatureChoices,
                'required' => false,
                'placeholder' => '-- Choisir une candidature --',
                'label' => 'Candidature',
                'constraints' => [
                    new NotBlank(['message' => 'La candidature est obligatoire.']),
                ],
            ])
            ->add('idUtilisateur', ChoiceType::class, [
                'choices' => $utilisateurChoices,
                'required' => false,
                'placeholder' => '-- Choisir un utilisateur --',
                'label' => 'Utilisateur',
                'constraints' => [
                    new NotBlank(['message' => 'L utilisateur est obligatoire.']),
                ],
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
