# FlightMemory.com Exporter 

Export your [FlightMemory.com](http://www.flightmemory.com) data to [OpenFlights.org CSV Format](http://openflights.org/help/csv.html).

##  General Notes 

[FlightMemory.com](http://www.flightmemory.com) can allow you to store various flight info, but does not have a free and easy-to-use tool to export your flight dat
a for safe-keeping, or for exporting to a different site.  This tool will do just that.  Given your  [FlightMemory.com](http://www.flightmemory.com) account information, this tool will log into [FlightMemory.com](http://www.flightmemory.com) and export your data.  Please read the [FlightMemory.com Terms of Service]([http://www.flightmemory.com/signin/?go=termsofservice).

## Installation (Web Server)

1. Modify the URL in the HTML form in the file "html/flightmemory_exporter.html".  The relevant line of code is shown below: <pre lang="html"><code>form action="<b>http://www.nilshomer.com/cgi/flightmemory/flightmemory.cgi</b>" method="POST" name="LoginForm"</code></pre>
2. Place the "html/flightmemory_exporter.html" file in an accessible place on your web-server.
3. Modify the "cgi/flightmemory.cgi" file to contain your path to python: <pre lang="python"><code>#!/home/content/n/i/l/nilshomer/bin/python</code></pre>
4. Place the three files located in the "cgi" folder in your web-server's "cgi" folder (and remember to make them executable!).

## Command Line

1. "cd" into the "cgi" directory.
2. Run the following for the command line options:<pre lang="python"><code>python flightmemory.cgi</code></pre>
