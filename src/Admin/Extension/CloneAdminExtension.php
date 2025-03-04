<?php

namespace Jorrit\SonataCloneActionBundle\Admin\Extension;

use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Jorrit\SonataCloneActionBundle\Controller\CloneController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

class CloneAdminExtension extends AbstractAdminExtension
{
    public const REQUEST_ATTRIBUTE = '_clone_subject';

    /**
     * @var PropertyListExtractorInterface
     */
    private $propertyInfoExtractor;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        PropertyListExtractorInterface $propertyInfoExtractor,
        RequestStack $requestStack
    ) {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->requestStack = $requestStack;
    }

    public function getAccessMapping(AdminInterface $admin): array
    {
        return [
            'clone' => 'CREATE',
        ];
    }

    public function alterNewInstance(AdminInterface $admin, $object): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return;
        }

        $subject = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        $subjectclass = get_class($subject);

        $idfields = $admin->getModelManager()->getIdentifierFieldNames($subjectclass);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $properties = $this->propertyInfoExtractor->getProperties($subjectclass);

        foreach ($properties as $property) {
            // Skip identifier fields.
            if (\in_array($property, $idfields, true)) {
                continue;
            }

            // Skip unwritable fields.
            if (!$propertyAccessor->isWritable($object, $property)) {
                continue;
            }

            // Skip unreadable fields.
            if (!$propertyAccessor->isReadable($subject, $property)) {
                continue;
            }

            $propertyAccessor->setValue($object, $property, $propertyAccessor->getValue($subject, $property));
        }
    }

    public function configureRoutes(AdminInterface $admin, RouteCollectionInterface $collection): void
    {
        $collection->add(
            'clone',
            $admin->getRouterIdParameter().'/clone',
            [
                '_controller' => CloneController::class,
            ]
        );
    }

    public function configureListFields(ListMapper $listMapper): void
    {
        $itemkeys = $listMapper->keys();

        foreach ($itemkeys as $itemkey) {
            $item = $listMapper->get($itemkey);
            if (($actions = $item->getOption('actions')) && isset($actions['clone'])) {
                $actions['clone']['template'] = '@SonataCloneAction/SonataAdmin/CRUD/list__action_clone.html.twig';
                $item->setOption('actions', $actions);
            }
        }
    }
}
