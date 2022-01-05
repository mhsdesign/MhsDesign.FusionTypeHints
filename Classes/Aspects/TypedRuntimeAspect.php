<?php

namespace MhsDesign\FusionTypeHints\Aspects;

use MhsDesign\FusionTypeHints\Fusion\TypedRuntime;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Fusion\Core\Runtime;
use Neos\Neos\Domain\Service\FusionService;

/**
 * This Aspect will make sure that the vanilla FusionView uses the Typed Runtime.
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class TypedRuntimeAspect
{
    /**
     * @Flow\Around("setting(MhsDesign.FusionTypeHints.aop.enableTypedRuntimeForFusionService) && method(Neos\Neos\Domain\Service\FusionService->createRuntime())")
     * @param JoinPointInterface $joinPoint
     */
    public function useTypedRuntime(JoinPointInterface $joinPoint): Runtime
    {
        $currentSiteNode = $joinPoint->getMethodArgument('currentSiteNode');
        $controllerContext = $joinPoint->getMethodArgument('controllerContext');

        /** @var FusionService $fusionService */
        $fusionService = $joinPoint->getProxy();
        $fusionObjectTree = $fusionService->getMergedFusionObjectTree($currentSiteNode);

        $fusionRuntime = new TypedRuntime($fusionObjectTree, $controllerContext);
        return $fusionRuntime;
    }
}
