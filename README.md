#testman

(PHP 5 >= 5.3.0)

![my image](testman.png)

##Download
	$ curl -LO http://git.io/testman.phar

##Quick test

	$ php testman.phar <path>

##Code Coverage Analysis

	$ php testman.phar <path> --coverage <file>


##Options
\--coverage <file>

\-c

	Generate code coverage report in XML format.


\--libdir <directory>

	Target library of code coverage.


\--output <file>

\-o

	Generate report in XML format.


\--outputdir <directory>

	Output base directory	


\--ssl_verify <boolean>

	CURLOPT_SSL_VERIFYHOST


