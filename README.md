# PHP-RRD-Graphing
A simple on-demand PHP based RRD Parser to provide a very basic alternative to mrtg-rrd.cgi which is no longer supported on modern Linux systems.

# Function
This simple php script leverages on the php module rrd_graph() and RRDGraph() depending on wether caching is desired.
If Cached Method is selected, it leverages on rrd_graph() and does the following:
* Checks if a cached image exists (and is not expired).
* Generates a fresh PNG file (write to disk in "./cache/") and pipe the contents of said file to the client.
* Cache the file for specific standard intervals (1 day, 7 days, 30 days, 1 year) for a configured time.

If cache is not enabled, it leverages on RRDGraph() and just directly dumps image data to the client
* Method is notably faster as it skips any disk writes but may require more processing power as it lacks image caching.

# Usage
At the moment, it is assumed that the mrtg configuration is set up to write all RRD files in a "host"."resource".rrd format in the same directory as the script is located. The location of the rrd files can be adjusted, as long as the httpd can read the files.
The PHP file looks for the following variables in the GET request:
* $host - The identifier of which system you are graphing (e.g. "localhost")
* $res - The resource the data is being pulled from (e.g. "eth0")
* $start - Sets how many days in the past it should start (e.g. "7")
* $end - Optionally set how many days in the past it should end (e.g. "6"). Must be less than $start
* $type - Optionally set the type of the data: b for Bytes, p for Percent, v for Volts, h for Hz. Default: b
* $ds0 - Set the name for "Data Source 0" in the rrd file. Default: "input"
* $ds1 - set the name for "Data Source 1" in the rrd file. Default: "output"
* $ds0color - set the line color for ds0 in HTML color coding without the #. Default: 00ff00
* $ds1color - set the line color for ds1 in HTML color coding without the #. Default: 0000ff

# Issues
* Missing proper input sanitizing
* When multiple instances are called on a single HTML page, and the "cached" method is used, the graph images don't get fully loaded. As a result, you may need to refresh the page in order to get the proper graphs. Note that refreshing only works with the "cached" images as the rest is generated on the fly at all time.
* Start and End variables not supporting AT Time specification
