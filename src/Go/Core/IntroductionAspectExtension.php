<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use ReflectionMethod;
use ReflectionProperty;
use Go\Aop\Aspect;
use Go\Aop\Framework;
use Go\Aop\Support;
use Go\Lang\Annotation;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension extends AbstractAspectLoaderExtension
{

    /**
     * Introduction aspect loader works with annotations from aspect
     *
     * For extension that works with annotations additional metaInformation will be passed
     *
     * @return string
     */
    public function getKind()
    {
        return self::KIND_ANNOTATION;
    }

    /**
     * Introduction aspect loader works only with properties of aspect
     *
     * @return string|array
     */
    public function getTarget()
    {
        return self::TARGET_PROPERTY;
    }

    /**
     * Checks if loader is able to handle specific point of aspect
     *
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return boolean true if extension is able to create an advisor from reflection and metaInformation
     */
    public function supports(Aspect $aspect, $reflection, $metaInformation = null)
    {
        return
            ($metaInformation instanceof Annotation\DeclareParents && IS_MODERN_PHP) ||
            ($metaInformation instanceof Annotation\DeclareError);
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param AspectContainer $container Instance of container
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information
     *
     * @throws \UnexpectedValueException
     */
    public function load(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation = null)
    {
        $pointcut    = $this->parsePointcut($aspect, $reflection, $metaInformation);
        $propertyId  = sprintf("%s->%s", $reflection->class, $reflection->name);

        switch (true) {
            case ($metaInformation instanceof Annotation\DeclareParents):
                $interface = $metaInformation->interface;
                $implement = $metaInformation->defaultImpl;
                $advice    = new Framework\TraitIntroductionInfo($interface, $implement);
                $advisor   = new Support\DeclareParentsAdvisor($pointcut->getClassFilter(), $advice);
                $container->registerAdvisor($advisor, $propertyId);
                break;

            case ($metaInformation instanceof Annotation\DeclareError):
                $reflection->setAccessible(true);
                $message = $reflection->getValue($aspect);
                $level   = $metaInformation->level;
                $advice  = new Framework\DeclareErrorInterceptor($message, $level);
                $container->registerAdvisor(new Support\DefaultPointcutAdvisor($pointcut, $advice), $propertyId);
                break;

            default:
                throw new \UnexpectedValueException("Unsupported pointcut class: " . get_class($pointcut));

        }
    }
}
