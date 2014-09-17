> phpcore is a framework

## Installation

Add package to your **composer.json**:

```json
{
  "require": {
    "mojosoft/phpcore": "dev-master"
  }
}
```

Run [composer](https://getcomposer.org/download/):

```bash
$ php composer.phar install -o
```

Now the entire toolchain is already available upon the vendor autoloader inclusion.

```php
<?php
// Load vendors
include 'vendor/autoload.php';

Route::on('/',function(){
	echo "Hello from Core!";
});

// Dispatch route
Route::dispatch();

// Send response to the browser
Response::send();
```


## Documentation

See the [wiki](https://github.com/caffeina-core/core/wiki).


## Contributing

How to get involved:

1. [Star](https://github.com/caffeina-core/core/stargazers) the project!
2. Answer questions that come through [GitHub issues](https://github.com/caffeina-core/core/issues?state=open)
3. [Report a bug](https://github.com/caffeina-core/core/issues/new) that you find


#### Pull Requests

Pull requests are **highly appreciated**.

Solve a problem. Features are great, but even better is cleaning-up and fixing issues in the code that you discover.


<p align="center"><a href="http://caffeina.co" target="_blank" title="Caffeina - Ideas Never Sleep"><img src="https://github.com/CaffeinaLab/BrandResources/blob/master/caffeina-handmade.png?raw=true" align="center" height="65"></a></p>
