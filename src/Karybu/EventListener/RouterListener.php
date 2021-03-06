<?php
/**
 * User: florin
 * Date: 1/30/13
 * Time: 11:49 AM
 */
namespace Karybu\EventListener;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Karybu\Routing\Router;

class RouterListener implements EventSubscriberInterface
{
    protected $context;
    protected $matcher;
    protected $logger;

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::REQUEST => array(array('routingListener', 32)));
    }

    /**
     * Constructor.
     *
     * @param Router                                      $router complete Router object, with matcher and context and all
     * @param RequestContext|null                         $context The RequestContext (can be null when $matcher implements RequestContextAwareInterface)
     * @param LoggerInterface|null                        $logger  The logger
     */
    public function __construct(Router $router, LoggerInterface $logger = null)
    {
        $this->matcher = $router->getMatcher();
        $this->context = $router->getContext();
        $this->logger = $logger;
    }

    public function routingListener(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $oContext = $request->attributes->get('oContext');

        // initialize the context that is also used by the generator (assuming matcher and generator share the same context instance)
        $this->context->fromRequest($request);
        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }
        // add attributes based on the request (routing)
        try {
            // matching a request is more powerful than matching a URL path + context, so try that first
            if ($this->matcher instanceof RequestMatcherInterface) {
                $parameters = $this->matcher->matchRequest($request);
            } else {
                $parameters = $this->matcher->match($request->getPathInfo());
            }
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], $this->parametersToString($parameters)));
            }
            // for forms that forgot about their action
            // TODO study this if more
            if ($request->isMethod('post') && !$request->isXmlHttpRequest() && isset($parameters['act'])) {
                if (substr($parameters['act'], 0, 4) != 'disp') {
                    unset($parameters['act']);
                }
            }

            $request->attributes->add($parameters);
            unset($parameters['_route']);
            unset($parameters['_controller']);
            $request->attributes->set('_route_params', $parameters);

            // add route parameters to Karybu context as if they came from htaccess
            foreach ($parameters as $name=>$value) {
                if (!is_numeric($name)) {
                    $oContext->set($name, $value, true);
                }
            }

            //TODO solve circular reference for better integration?
            $oContext->set('request', $request);
        } catch (ResourceNotFoundException $e) {
            $message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());
            throw new NotFoundHttpException($message, $e);
        } catch (MethodNotAllowedException $e) {
            $message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), strtoupper(implode(', ', $e->getAllowedMethods())));
            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
        }
    }


    protected function parametersToString(array $parameters)
    {
        $pieces = array();
        foreach ($parameters as $key => $val) {
            $pieces[] = sprintf('"%s": "%s"', $key, (is_string($val) ? $val : json_encode($val)));
        }

        return implode(', ', $pieces);
    }

}
