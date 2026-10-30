<?php

namespace App\Form;

use App\Entity\Analysis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Defines the form structure for creating and ingesting an analysis workspace
 */
class AnalysisType extends AbstractType
{
    /**
     * Builds form fields and attaches validation constraints
     *
     * @param FormBuilderInterface $builder Symfony form builder
     * @param array<string, mixed> $options Form configuration options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Audit Title',
                'attr' => [
                    'placeholder' => 'e.g., Q4 Enterprise Cloud Contract Audit',
                    'class' => 'input input-bordered w-full',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Please provide a title for this audit.',
                    ),
                    new Length(
                        min: 3,
                        max: 255,
                        minMessage: 'The title must be at least {{ limit }} characters long.',
                        maxMessage: 'The title cannot exceed {{ limit }} characters.',
                    ),
                ],
            ])
            ->add('proposalFile', FileType::class, [
                'label' => 'Commercial Proposal (PDF, Markdown, or Image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'file-input file-input-bordered w-full',
                    'accept' => '.pdf,.md,.markdown,.txt,.png,.jpg,.jpeg,.webp',
                ],
                'constraints' => [
                    new NotNull(
                        message: 'Please upload the commercial proposal.',
                    ),
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'application/pdf',
                            'text/plain',
                            'text/markdown',
                            'text/x-markdown',
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                        maxSizeMessage: 'File size cannot exceed 10 MB.',
                        mimeTypesMessage: 'Unsupported format. Allowed formats: PDF, Markdown, PNG, JPG, or WEBP.',
                    ),
                ],
            ])
            ->add('technicalSpecFiles', FileType::class, [
                'label' => 'Technical Reference Specs (PDF, Markdown, or Image)',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'file-input file-input-bordered w-full',
                    'accept' => '.pdf,.md,.markdown,.txt,.png,.jpg,.jpeg,.webp',
                    'multiple' => 'multiple',
                ],
                'constraints' => [
                    new All(
                        constraints: [
                            new File(
                                maxSize: '10M',
                                mimeTypes: [
                                    'application/pdf',
                                    'text/plain',
                                    'text/markdown',
                                    'text/x-markdown',
                                    'image/png',
                                    'image/jpeg',
                                    'image/webp',
                                ],
                                maxSizeMessage: 'Each file cannot exceed 10 MB.',
                                mimeTypesMessage: 'Unsupported format. Allowed formats: PDF, Markdown, PNG, JPG, or WEBP.',
                            ),
                        ],
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Run Audit',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    /**
     * Configures the default data class options for the form
     *
     * @param OptionsResolver $resolver Options resolver instance
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Analysis::class,
        ]);
    }
}
