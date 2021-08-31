# EcommitMessengerSupervisorBundle

The EcommitMessengerSupervisorBundle bundle (for Symfony) manages [Messenger component](https://symfony.com/doc/current/components/messenger.html)
with [Supervisor](http://supervisord.org).


![Tests](https://github.com/e-commit/messenger-supervisor-bundle/workflows/Tests/badge.svg)


Available features :
* Start Supervisor programs (workers)
* Stop Supervisor programs (workers)
* Show Supervisor programs (workers) status
* Show Supervisor programs (workers) status with Nagios format
* After worker failure :
    * Stop Supervisor program (can be disabled)
    * Send email (can be disabled)


## Installation ##

Install the bundle with Composer : In your project directory, execute the following command :

```bash
$ composer require ecommit/messenger-supervisor-bundle:1.*@dev
```

Enable the bundle in the `config/bundles.php` file for your project :

```php
return [
    //...
    Ecommit\MessengerSupervisorBundle\EcommitMessengerSupervisorBundle::class => ['all' => true],
    //...
];
```

In Supervisor configuration enable the API and add your workers (See the [Supervisor doc](http://supervisord.org) for more details):

```ini
;eg in /etc/supervisor/conf.d/myconf.conf
[inet_http_server]
port = 127.0.0.1:9001
username = user
password = 123

[program:program_async]
command=php /path/to/your/app/bin/console messenger:consume async
process_name=%(program_name)s_%(process_num)02d
numprocs=1
autostart=true
autorestart=true
user=ubuntu

;You can define others programs (workers) :
;[program:program_async2]
;command=php /path/to/your/app/bin/console messenger:consume async2
;process_name=%(program_name)s_%(process_num)02d
;numprocs=2
;autostart=true
;autorestart=true
;user=ubuntu
```

Configure Messenger (See the [doc](https://symfony.com/doc/current/messenger.html) for more details) and 
Mailer (See the [doc](https://symfony.com/doc/current/mailer.html) for more details).

In your project, add the configuration file `config/packages/ecommit_messenger_supervisor.yaml` :

```yaml
ecommit_messenger_supervisor:
    supervisor:
        #Supervisor API configuration
        host: '127.0.0.1' #IP address - Required
        username: user #Username - Not required - Default value: null
        password: 123 #Password - Not required - Default value: null
        #port: 9001 #Port - Not required - Default value: 9001
        #timeout: 3600 #API timeout (in seconds) - Not required - Default value: 3600

    #Transports / Programs configuration :
    #Mapping "Messenger transport name" -> "Supervisor program name (group name)"
    transports:
        async: program_async #async = Messenger transport name | program_async = Supervisor program (group) name
        #Or you can set options :
        #async:
        #   failure:
        #       stop_program: true #Stop program after failure - Not required - Available values: "always", "will-not-retry" (only if the message cannot be retried), "never" - Default value : "always"
        #       send_mail: true #Send mail after failure - Not required - Available values: "always", "will-not-retry" (only if the message cannot be retried), "never" - Default value : "always"

        #You can define others programs :
        #async2: program_async2

    #Mailer configuration
    mailer:
        from: from@localhost #Sender - Required if a program is setting with send_mail=true option
        to: to@localhost #Recipient - Required if a program is setting with send_mail=true option
        #You can use multiple recipients:
        #to: ['to1@localhost', 'to2@localhost']
        #subject: "[Supervisor][<server>][<program>] Error" #Suject - Not required - Default value : "[Supervisor][<program>] Error"
        #<program> is replaced by Supervisor program (group) name
        #<server> is replaced by server name
```


## Usage ##

```bash
#Start a program
php bin/console ecommit:supervisor start program_async
#Start multiple programs
php bin/console ecommit:supervisor start program_async program_async2
#Start all programs
php bin/console ecommit:supervisor start  all


#Stop a program
php bin/console ecommit:supervisor stop program_async
#Stop many programs
php bin/console ecommit:supervisor stop program_async program_async2
#Stop all programs
php bin/console ecommit:supervisor stop  all


#Get status on a single program
php bin/console ecommit:supervisor status program_async
#Get status on multiple programs
php bin/console ecommit:supervisor status program_async program_async2
#Get status on all programs
php bin/console ecommit:supervisor status all
#Use can use Nagios format
php bin/console ecommit:supervisor status all --nagios
```

You can also use the `Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor` service :

```php
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;

class MyClass
{
    protected $supervisor;
    
    public function __construct(Supervisor $supervisor) //Supervisor service is injected
    {
        $this->supervisor = $supervisor;
    }

    public function myMethod(): void
    {
        //$this->supervisor->startProgram('program_async');
        //$this->supervisor->stopProgram('program_async');
        //$status = $this->supervisor->getProgramsStatus(['program_async']);
    }
}
```

## License ##

This bundle is available under the MIT license. See the complete license in the *LICENSE* file.
