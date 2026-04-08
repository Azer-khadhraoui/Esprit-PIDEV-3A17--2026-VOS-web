<?php
namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('cv', FileType::class, [
                'label' => 'CV (PDF)',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => [
                    new NotBlank(['message' => 'Le CV est obligatoire.']),
                    new File(['maxSize' => '5M', 'mimeTypes' => ['application/pdf']])
                ],
                'attr' => ['accept' => '.pdf']
            ])
            ->add('lettre_motivation', FileType::class, [
                'label' => 'Lettre de Motivation (PDF)',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => [
                    new NotBlank(['message' => 'La lettre de motivation est obligatoire.']),
                    new File(['maxSize' => '5M', 'mimeTypes' => ['application/pdf']])
                ],
                'attr' => ['accept' => '.pdf']
            ])
            ->add('niveau_experience', ChoiceType::class, [
                'label' => 'Niveau d\'Expérience',
                'choices' => [
                    'Débutant' => 'Débutant',
                    'Junior'   => 'Junior',
                    'Confirmé' => 'Confirmé',
                    'Senior'   => 'Senior',
                    'Expert'   => 'Expert',
                ],
                'placeholder' => '-- Choisir --',
                'required' => true,
            ])
            ->add('annees_experience', IntegerType::class, [
                'label' => 'Années d\'Expérience',
                'attr'  => ['min' => 0, 'max' => 50, 'placeholder' => 'Ex: 3'],
                'required' => true,
            ])
            ->add('domaine_experience', TextType::class, [
                'label' => 'Domaine d\'Expérience',
                'attr'  => ['placeholder' => 'Ex: Cloud Computing, Développement Web...'],
                'required' => true,
            ])
            ->add('dernier_poste', TextType::class, [
                'label'    => 'Dernier Poste',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Développeur Backend']
            ])
            ->add('message_candidat', TextareaType::class, [
                'label'    => 'Message (optionnel)',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Présentez-vous brièvement...']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}