<?php

/*
 * This file is part of the RichModelFormsBundle package.
 *
 * (c) Christian Flothmann <christian.flothmann@sensiolabs.de>
 * (c) Christopher Hertel <christopher.hertel@sensiolabs.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SensioLabs\RichModelForms\DataTransformer;

use SensioLabs\RichModelForms\ExceptionHandling\ExceptionHandlerRegistry;
use SensioLabs\RichModelForms\ExceptionHandling\ExceptionToErrorMapperTrait;
use SensioLabs\RichModelForms\Instantiator\ViewDataInstantiator;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class ValueObjectTransformer implements DataTransformerInterface
{
    use ExceptionToErrorMapperTrait;

    private $propertyAccessor;
    private $form;
    private $exceptionToErrorMapper;

    public function __construct(ExceptionHandlerRegistry $exceptionHandlerRegistry, PropertyAccessorInterface $propertyAccessor, FormBuilderInterface $form)
    {
        $this->exceptionHandlerRegistry = $exceptionHandlerRegistry;
        $this->propertyAccessor = $propertyAccessor;
        $this->form = $form;
    }

    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if ($this->form->getCompound()) {
            $viewData = [];

            foreach ($this->form as $name => $child) {
                if ($child instanceof ButtonBuilder) {
                    continue;
                }
                $viewData[$name] = $this->getPropertyValue($child, $value);
            }

            return $viewData;
        }

        return $this->getPropertyValue($this->form, $value);
    }

    public function reverseTransform($value)
    {
        try {
            return (new ViewDataInstantiator($this->form, $value))->instantiateObject();
        } catch (\Throwable $e) {
            $error = $this->mapExceptionToError($this->form, $value, $e);

            if (null !== $error) {
                throw new TransformationFailedException(strtr($error->getMessageTemplate(), $error->getParameters()), 0, $e);
            }

            throw $e;
        }
    }

    private function getPropertyValue(FormBuilderInterface $form, $object)
    {
        if (null !== $form->getPropertyPath()) {
            return $this->propertyAccessor->getValue($object, $form->getPropertyPath());
        }

        return $this->propertyAccessor->getValue($object, new PropertyPath($form->getName()));
    }
}
