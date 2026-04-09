<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Le prenom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'L email est obligatoire.']),
                    new Email(['message' => 'Email invalide.']),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image de profil',
                'mapped' => false,
                'required' => false,
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Client' => 'CLIENT',
                    'Admin RH' => 'ADMIN_RH',
                    'Admin Technique' => 'ADMIN_TECHNIQUE',
                ],
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Le role est obligatoire.']),
                    new Choice(['choices' => ['CLIENT', 'ADMIN_RH', 'ADMIN_TECHNIQUE']]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
