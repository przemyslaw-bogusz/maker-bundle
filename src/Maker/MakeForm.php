<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Maker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineEntityHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Validation;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
final class MakeForm extends AbstractMaker
{
    private $entityHelper;
    private $boundEntityOrClass = true;

    public function __construct(DoctrineEntityHelper $entityHelper)
    {
        $this->entityHelper = $entityHelper;
    }

    public static function getCommandName(): string
    {
        return 'make:form';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf)
    {
        $command
            ->setDescription('Creates a new form class')
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('The name of the form class (e.g. <fg=yellow>%sType</>)', Str::asClassName(Str::getRandomTerm())))
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/MakeForm.txt'))
        ;

        $inputConf->setArgumentAsNonInteractive('name');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (is_null($input->getArgument('name'))) {
            $argument = $command->getDefinition()->getArgument('name');
            $question = new Question($argument->getDescription());
            $value = $io->askQuestion($question);

            $input->setArgument('name', $value);

            if ($this->entityHelper->isDoctrineConnected()) {
                $question = new Question('Enter the class or entity name that the new form will be bound to (empty for none)');
                $question->setAutocompleterValues($this->entityHelper->getEntitiesForAutocomplete());
                $this->boundEntityOrClass = $io->askQuestion($question);
            }
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $formClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Form\\',
            'Type'
        );

        if (null === $this->boundEntityOrClass) {

            $generator->generateClass(
                $formClassNameDetails->getFullName(),
                'form/SimpleType.tpl.php',
                [ ]
            );

        } else {

            $entityClassNameDetails = $generator->createClassNameDetails(
                true === $this->boundEntityOrClass ? $formClassNameDetails->getRelativeNameWithoutSuffix() : $this->boundEntityOrClass,
                'Entity\\'
            );

            $entityClassExists = class_exists($entityClassNameDetails->getFullName());

            $formFields = $entityClassExists ? $this->entityHelper->getFormFieldsFromEntity($entityClassNameDetails->getFullName()) : ['field_name'];

            $generator->generateClass(
                $formClassNameDetails->getFullName(),
                'form/Type.tpl.php',
                [
                    'entity_class_exists' => $entityClassExists,
                    'entity_full_class_name' => $entityClassNameDetails->getFullName(),
                    'entity_class_name' => $entityClassNameDetails->getShortName(),
                    'form_fields' => $formFields,
                ]
            );

        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Add fields to your form and start using it.',
            'Find the documentation at <fg=yellow>https://symfony.com/doc/current/forms.html</>',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            AbstractType::class,
            // technically only form is needed, but the user will *probably* also want validation
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator',
            // add as an optional dependency: the user *probably* wants validation
            false
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm',
            false
        );
    }
}
