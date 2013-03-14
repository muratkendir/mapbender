<?php

namespace Mapbender\CoreBundle\DataFixtures\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mapbender\CoreBundle\Entity\Application as ApplicationEntity;
use Mapbender\CoreBundle\Entity\Element;
use Mapbender\CoreBundle\Component\Element as ElementComponent;
use Mapbender\CoreBundle\Entity\Layerset;

/**
 * The class LoadApplicationData loads the applications from the "mapbender.yml"
 * into a mapbender database.
 * 
 * @author Paul Schmidt
 */
class LoadApplicationData implements FixtureInterface, ContainerAwareInterface
{

    /**
     * Container
     * 
     * @var ContainerInterface 
     */
    private $container;

    /**
     * @inheritdoc
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager)
    {
        $definitions = $this->container->getParameter('applications');
        foreach($definitions as $slug => $definition)
        {
            $timestamp = round((microtime(true) * 1000));
            // First, create an application entity
            $application = new ApplicationEntity();
            $application
                    ->setSlug($timestamp."_".$slug)
                    ->setTitle($timestamp." ".$definition['title'])
                    ->setDescription($definition['description'])
                    ->setTemplate($definition['template'])
                    ->setPublished($definition['published'])
                    ->setUpdated(new \DateTime('now'));
            if(array_key_exists('extra_assets', $definition))
            {
                $application->setExtraAssets($definition['extra_assets']);
            }
            $owner = $this->container->get('doctrine')
                    ->getRepository('FOMUserBundle:User')
                    ->find(1);
            $application->setOwner($owner);

            $application->yaml_roles = array();
            if(array_key_exists('roles', $definition))
            {
                $application->yaml_roles = $definition['roles'];
            }
            $manager->persist($application);
            $layersets_map = array();
            foreach($definition['layersets'] as $layersetName => $layersetDef)
            {
                $layerset = new Layerset();
                $layerset->setTitle($layersetName);
                $layerset->setApplication($application);
                $manager->persist($layerset);
                $application->addLayerset($layerset);
                $manager->flush();
                $layersets_map[$layersetName] = $layerset->getId();
            }
            $manager->persist($application);
            $elements_map = array();
            // Then create elements
            foreach($definition['elements'] as $region => $elementsDefinition)
            {
                if($elementsDefinition !== null)
                {
                    $weight = 0;
                    foreach($elementsDefinition as $element_yml_id => $elementDefinition)
                    {
                        $class = $elementDefinition['class'];
                        $title = array_key_exists('title', $elementDefinition) ?
                                $elementDefinition['title'] :
                                $class::getClassTitle();

                        $element = new Element();

                        $element->setClass($elementDefinition['class'])
                                ->setTitle($title)
                                ->setConfiguration($elementDefinition)
                                ->setRegion($region)
                                ->setWeight($weight++)
                                ->setApplication($application);
                        //TODO: Roles
                        $application->addElements($element);
                        $manager->persist($element);
                        $manager->flush();
                        $elements_map[$element_yml_id] = $element->getId();
                    }
                }
            }
//            print_r($elements_map);
//            print_r($layersets_map);
            // Then merge default configuration and elements configuration
            foreach($application->getElements() as $element)
            {      
                $configuration_yml = $element->getConfiguration();
//                if(key_exists("target", $configuration_yml)
//                        && key_exists($configuration_yml["target"], $elements_map))
//                {
//                    $configuration_yml["target"] = $elements_map[$configuration_yml["target"]];
//                }
//                if(key_exists("layerset", $configuration_yml)
//                        && key_exists($configuration_yml["layerset"], $layersets_map))
//                {
//                    $configuration_yml["layerset"] = $layersets_map[$configuration_yml["layerset"]];
//                }
                $entity_class = $configuration_yml['class'];
                $appl = new \Mapbender\CoreBundle\Component\Application($this->container, $application, array());
                $elComp = new $entity_class($appl, $this->container, new Element());
                unset($configuration_yml['class']);
                unset($configuration_yml['title']);

                $configuration = ElementComponent::mergeArrays(
                                $elComp->getDefaultConfiguration(),
                                $configuration_yml, array());
                
                if(key_exists("target", $configuration)
                       && $configuration["target"] !== null
                        && key_exists($configuration["target"], $elements_map))
                {
                    $configuration["target"] = $elements_map[$configuration["target"]];
                }
                if(key_exists("layerset", $configuration_yml)
                       && $configuration["layerset"] !== null
                        && key_exists($configuration["layerset"], $layersets_map))
                {
                    $configuration["layerset"] = $layersets_map[$configuration["layerset"]];
                }
                
                $class = $elementDefinition['class'];
                $title = array_key_exists('title', $elementDefinition) ?
                        $elementDefinition['title'] :
                        $class::getClassTitle();
                $element->setConfiguration($configuration);
                $manager->persist($element);
            }
            $manager->flush();
        }
    }

}
