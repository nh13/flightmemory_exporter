#!/home/content/n/i/l/nilshomer/bin/python

# http://www.opensource.org/licenses/mit-license.php MIT License

import mechanize
import cookielib
import sys
import tempfile
import os
import subprocess
import re
from optparse import OptionParser
import cgi
import cgitb; cgitb.enable()

def main(username, password, comments):
    url_login = 'http://www.flightmemory.com/'

    # open a browser
    br=mechanize.Browser()

    # set cookies
    cj = cookielib.LWPCookieJar()
    br.set_cookiejar(cj)

    # debug
    #br.set_debug_http(True)
    #br.set_debug_redirects(True)
    #br.set_debug_responses(True)

    # go through the login page
    br.open(url_login)
    br.select_form(name="ll") 
    br['username']=username
    br['passwort']=password
    br.submit()

    # go to the flight data page
    links = list()
    for link in br.links(text_regex="FLIGHTDATA"):
        links.append(link)
    if 0 == len(links):
        print "Error: login was likely unsuccessful\n"
        sys.stderr.write("Error: login was likely unsuccessful\n")
        sys.exit(1)
    elif 1 < len(links):
        print "Error: one link expected\n"
        sys.stderr.write("Error: one link expected\n")
        sys.exit(1)
    req = br.click_link(text="FLIGHTDATA")
    br.open(req)

    first = "True"
    dbpos = 50
    while True:
        # do something
        sys.stderr.write("Examining: " + br.geturl() + "\n")
        f = tempfile.NamedTemporaryFile(delete=False)
        f.write(br.response().read())
        f.close()
        output = subprocess.check_output("php import.php %s %s %s" % (f.name, first, str(comments)), shell=True)
        sys.stdout.write(output)
        os.unlink(f.name)

        # follow links to the next page
        # NB: the pattern is important here so that if dbpos=50 we do not choose dbpos=500 too
        links = list()
        pattern = re.compile("%s'" % str(dbpos))
        for link in br.links(url_regex='flugdaten&dbpos=' + str(dbpos)):
            if "[IMG]" == link.text:
                if re.search(pattern, str(link)):
                    links.append(link)
        if 0 == len(links):
            #sys.stderr.write("Last page reached\n")
            break
        elif 2 != len(links) and 4 != len(links):
            sys.stderr.write("Error: one link expected [%d]\n" % len(links))
            sys.exit(1)
        for i in range(1, len(links)):
            if links[0].url != links[i].url:
                sys.stderr.write("Error: links do not match\n")
                sys.exit(1)

        # go to the next page
        req = br.click_link(url=links[0].url)
        br.open(req)

        # next 50 flights
        dbpos += 50
        first = "False"

if 'GATEWAY_INTERFACE' in os.environ:
    # get the info from the html form
    form = cgi.FieldStorage()
    username = form['username'].value
    password = form['password'].value
    comments = form['comments'].value
    #set up the html stuff
    print "Content-Type: text/plain\n"
else:
    parser = OptionParser()
    parser.add_option('--username', help="your flightmemory.com username", default=None, dest="username")
    parser.add_option('--password', help="your flightmemory.com password", default=None, dest="password")
    parser.add_option('--comments', help="do not include notes/comments", action="store_false", default=True, dest="comments")
    options, args = parser.parse_args()
    if None == options.username:
        sys.stderr.write("No username given!\n\n")
        parser.print_help()
        sys.exit(1);
    if None == options.password:
        sys.stderr.write("No password given!\n\n")
        sys.exit(1)

    username = options.username
    password = options.password
    comments = options.comments

# run the script
main(username, password, comments)
