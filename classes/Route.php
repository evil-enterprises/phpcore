<?php

/**
 * Route
 *
 * URL Router and action dispatcher.
 *
 * @package core
 * @author stefano.azzolini@caffeinalab.com
 * @version 1.0
 * @copyright Caffeina srl - 2014 - http://caffeina.co
 */

namespace phpcore;

class Route
{
    use Module;

    protected static $routes;
    protected static $base    = '';
    protected static $prefix  = [];
    protected static $group   = [];

    protected $URLPattern     = '';
    protected $pattern        = '';
    protected $dynamic        = false;
    protected $callback       = null;
    protected $methods        = [];
    protected $middlewares    = [];
    protected $afters         = [];

    protected $rules          = [];

    protected $response       = '';

    /**
     * Create a new route definition. This method permits a fluid interface.
     *
     * @param string $URLPattern The URL pattern, can be used named parameters for variables extraction
     * @param callable $callback The callback to invoke on route match.
     * @param string $method The HTTP method for which the route must respond.
     * @return Route
     */
    public function __construct($URLPattern,callable $callback = null,$method='get')
    {
        $this->URLPattern = rtrim(implode('',static::$prefix),'/') . '/' . trim($URLPattern,'/')?:'/';
        if ($this->URLPattern!='/')
        {
            $this->URLPattern = rtrim($this->URLPattern,'/');
        }
        $this->dynamic = $this->isDynamic($this->URLPattern);
        $this->pattern=$this->URLPattern;
        if ($this->dynamic)
        {
            $this->compilePatternAsRegex($this->URLPattern,$this->rules);
        }
        $this->callback = $callback;

        // We will use hash-checks, for O(1) complexity vs O(n)
        $this->methods[$method] = 1;
        return static::add($this);
    }

    /**
     * Check if route match on a specified URL and HTTP Method.
     * @param  [type] $URL The URL to check against.
     * @param  string $method The HTTP Method to check against.
     * @return boolean
     */
    public function match($URL,$method='get')
    {
        $method = strtolower($method);
        $matched=false;
        // * is an http method wildcard
        if(empty($this->methods[$method]) && empty($this->methods['*']))
        {
            return false;
        }
        $URL = rtrim($URL,'/');
        $args = [];


        if($this->dynamic)
        {
            $matched=preg_match($this->pattern,$URL,$args);
        }
        else
        {
            $matched=($URL == rtrim($this->pattern,'/'));
        }

        if ($matched)
        {
            if($args)
            {
                foreach ($args as $key => $value)
                {
                    if(false===is_string($key))
                    {
                        unset($args[$key]);
                    }
                }
            }
            return $args;
        }
        return false;
    }

    /**
     * Run one of the mapped callbacks to a passed HTTP Method.
     * @param  array  $args The arguments to be passed to the callback
     * @param  string $method The HTTP Method requested.
     * @return array The callback response.
     */
    public function run(array $args,$method='get')
    {
        $method = strtolower($method);
        if(Request::method() !== $method)
        {
            return false;
        }

        // Call direct middlewares
        if($this->middlewares)
        {
            // Reverse middlewares order
            foreach (array_reverse($this->middlewares) as $mw)
            {
                $mw_result = call_user_func($mw->bindTo($this));
                if (false  === $mw_result)
                {
                    return [''];
                }
            }
        }

        $callback=$this->callback;
        if (is_array($this->callback))
        {
            if (array_key_exists($method,$this->callback))
            {
                $callback=$this->callback[$method];
            }
        }

        Event::trigger('core.route.before',$this);

        // Start capturing output
        Response::start();

        $this->response = '';
        $view_results = call_user_func_array($callback->bindTo($this), $args);

        // Render View if returned, else echo string or encode json response
        if(null !== $view_results)
        {
            if (is_a($view_results,'View') || is_string($view_results))
            {
                echo $this->response = (string)$view_results;
            }
            else
            {
                Response::json($view_results);
            }
        }

        Response::end();

        // Apply afters
        if($this->afters)
        {
            // Reverse middlewares order
            foreach (array_reverse($this->afters) as $cb) {
                call_user_func($cb->bindTo($this));
            }
        }

        Event::trigger('core.route.after',$this);
        return [$this->response];
    }

    /**
     * Check if route match URL and HTTP Method and run if it is valid.
     * @param  [type] $URL The URL to check against.
     * @param  string $method The HTTP Method to check against.
     * @return array The callback response.
     */
    public function runIfMatch($URL,$method='get')
    {
        return ($args = $this->match($URL,$method)) ? $this->run($args,$method) : null;
    }

    /**
     * Start a route definition, default to HTTP GET.
     * @param  string $URLPattern The URL to match against, you can define named segments to be extracted and passed to the callback.
     * @param  callable $callback The callback to be invoked (with variables extracted from the route if present) when the route match the request URI.
     * @return Route
     */
    public static function on($URLPattern,callable $callback = null)
    {
        return new Route($URLPattern,$callback);
    }

    /**
     * Start a route definition, for any HTTP Method (using * wildcard).
     * @param  string $URLPattern The URL to match against, you can define named segments to be extracted and passed to the callback.
     * @param  callable $callback The callback to be invoked (with variables extracted from the route if present) when the route match the request URI.
     * @return Route
     */
    public static function any($URLPattern,callable $callback = null)
    {
        return (new Route($URLPattern,$callback))->via('*');
    }

    /**
     * Bind a callback to the route definition
     * @param  callable $callback The callback to be invoked (with variables extracted from the route if present) when the route match the request URI.
     * @return Route
     */
    public function & with(callable $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Bind a middleware callback to invoked before the route definition
     * @param  callable array $middlewares The callbacks to be invoked ($this is binded to the route object).
     * @return Route
     */
    public function & before(callable $middlewares)
    {
        foreach((array)$middlewares as $middleware)
        {
            $this->middlewares[] = $middleware;
        }
        return $this;
    }

    /**
     * Bind a middleware callback to invoked after the route definition
     * @param  callable array $callback The callbacks to be invoked ($this is binded to the route object).
     * @return Route
     */
    public function & after(callable $callbacks)
    {
        foreach((array)$callbacks as $callback)
        {
            $this->afters[] = $callback;
        }
        return $this;
    }

    /**
     * Defines the HTTP Methods to bind the route onto.
     *
     * Example:
     * <code>
     *  Route::on('/test')->via('get','post','delete');
     * </code>
     *
     * @return Route
     */
    public function & via()
    {
        $methodsmap = [];
        foreach(func_get_args() as $method)
        {
            $methodsmap[strtolower($method)] = 1;
        }
        $this->methods = $methodsmap;
        return $this;
    }

    /**
     * Defines the regex rules for the named parameter in the current URL pattern
     *
     * Example:
     * <code>
     *  Route::on('/proxy/:number/:url')
     *    ->rules([
     *      'number'  => '\d+',
     *      'url'     => '.+',
     *    ]);
     * </code>
     *
     * @param  array  $rules The regex rules
     * @return Route
     */
    public function & rules(array $rules)
    {
        foreach((array)$rules as $varname => $rule)
        {
            $this->rules[$varname] = $rule;
        }
        $this->pattern = $this->compilePatternAsRegex($this->URLPattern,$this->rules);
        return $this;
    }

    /**
     * Map a HTTP Method => callable array to a route.
     *
     * Example:
     * <code>
     *  Route::map('/test'[
     *      'get'     => function(){ echo "HTTP GET"; },
     *      'post'    => function(){ echo "HTTP POST"; },
     *      'put'     => function(){ echo "HTTP PUT"; },
     *      'delete'  => function(){ echo "HTTP DELETE"; },
     *    ]);
     * </code>
     *
     * @param  string $URLPattern The URL to match against, you can define named segments to be extracted and passed to the callback.
     * @param  array $callbacks The HTTP Method => callable map.
     * @return Route
     */
    public static function & map($URLPattern,$callbacks = [])
    {
        $route = new static($URLPattern);
        $route->callback = [];
        foreach ($callbacks as $method => $callback)
        {
            $method = strtolower($method);
            if(Request::method() !== $method)
            {
                continue;
            }
            $route->callback[$method] = $callback;
            $route->methods[$method] = 1;
        }
        return $route;
    }

    /**
     * Compile an URL schema to a PREG regular expression.
     * @param  string $pattern The URL schema.
     * @return string The compiled PREG RegEx.
     */
    protected static function compilePatternAsRegex($pattern,$rules=[])
    {
        return '#^'.preg_replace_callback('#:([a-zA-Z]\w*)#S',function($g) use (&$rules){
            return '(?<' . $g[1] . '>' . (isset($rules[$g[1]])?$rules[$g[1]]:'[^/]+') .')';
        },str_replace(['.',')'],['\.',')?'],$pattern)).'$#';
    }

    /**
     * Extract the URL schema variables from the passed URL.
     * @param  string  $pattern The URL schema with the named parameters
     * @param  string  $URL The URL to process, if omitted the current request URI will be used.
     * @param  boolean $cut If true don't limit the matching to the whole URL (used for group pattern extraction)
     * @return array The extracted variables
     */
    protected static function extractVariablesFromURL($pattern,$URL=null,$cut=false)
    {
        $URL = $URL?:Request::URI();
        $pattern = $cut ? str_replace('$#','',$pattern).'#' : $pattern;
        if(!preg_match($pattern,$URL,$args))
        {
            return false;
        }
        foreach ($args as $key => $value)
        {
            if(false===is_string($key)) unset($args[$key]);
        }
        return $args;
    }

    /**
     * Check if an URL schema need dynamic matching (regex).
     * @param  string  $pattern The URL schema.
     * @return boolean
     */
    protected static function isDynamic($pattern)
    {
        return
            (strpos($pattern,':')!==false) ||
            (strpos($pattern,'(')!==false) ||
            (strpos($pattern,'?')!==false) ||
            (strpos($pattern,'[')!==false) ||
            (strpos($pattern,'*')!==false) ||
            (strpos($pattern,'+')!==false);
    }

    /**
     * Add a route to the internal route repository.
     * @param Route $r
     * @return Route
     */
    public static function add(Route $r)
    {
        if(isset(static::$group[0]))
        {
            static::$group[0]->add($r);
        }
        return static::$routes[implode('',static::$prefix)][] = $r;
    }

    /**
     * Define a route group, if not immediatly matched internal code will not be invoked.
     * @param  string $prefix The url prefix for the internal route definitions.
     * @param  string $callback This callback is invoked on $prefix match of the current request URI.
     */
    public static function group($prefix,callable $callback=null)
    {

        // Skip definition if current request doesn't match group.

        $prefix_complete = rtrim(implode('',static::$prefix),'/') . $prefix;

        if(static::isDynamic($prefix))
        {

            // Dynamic group, capture vars
            $vars = static::extractVariablesFromURL(static::compilePatternAsRegex($prefix_complete),null,true);

            // Errors in compile pattern or variable extraction, aborting.
            if (false === $vars)
            {
                //TODO Throw Exception
                return;
            }

            static::$prefix[] = $prefix;
            if(empty(static::$group))
            {
                static::$group = [];
            }
            array_unshift(static::$group, new RouteGroup());
            if($callback)
            {
                call_user_func_array($callback,$vars);
            }
            $group = static::$group[0];
            array_shift(static::$group);
            array_pop(static::$prefix);
            if(empty(static::$prefix))
            {
                static::$prefix=[''];
            }
            return $group;

        }
        if (0 === strpos(Request::URI(), $prefix_complete))
        {
            // Static group
            static::$prefix[] = $prefix;
            if(empty(static::$group))
            {
                static::$group = [];
            }
            array_unshift(static::$group, new RouteGroup());
            if($callback)
            {
                call_user_func($callback);
            }
            $group = static::$group[0];
            array_shift(static::$group);
            array_pop(static::$prefix);
            if(empty(static::$prefix))
            {
                static::$prefix=[''];
            }
            return $group;
        }
    }

    /**
     * Start the route dispatcher and resolve the URL request.
     * @param  string $URL The URL to match onto.
     * @return boolean true if a route callback was executed.
     */
    public static function dispatch($URL=null)
    {
        if (null === $URL)
        {
            $URL = Request::URI();
        }
        $method = Request::method();
        foreach ((array)static::$routes as $group => $routes)
        {
            foreach ($routes as $route)
            {
                if(false !== ($args = $route->match($URL,$method)))
                {
                    $route->run($args,$method);
                    return true;
                }
            }
        }
        Response::error(404,'404 Resource not found.');
        Event::trigger(404);

    }
}

class RouteGroup
{
    protected $routes;

    public function __construct()
    {
        $this->routes = new SplObjectStorage;
    }

    public function has(Route $r){
        return $this->routes->contains($r);
    }

    public function add(Route $r)
    {
        $this->routes->attach($r); return $this;
    }

    public function remove(Route $r)
    {
        if($this->routes->contains($r))
        {
            $this->routes->detach($r);
        }
        return $this;
    }

    public function before(callable $callbacks)
    {
        foreach($this->routes as $route)
        {
            $route->before($callbacks);
        }
        return $this;
    }

    public function after(callable $callbacks)
    {
        foreach($this->routes as $route)
        {
            $route->after($callbacks);
        }
        return $this;
    }
}

