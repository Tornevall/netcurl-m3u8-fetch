# The library

The library used can be found at [http://netcurl.org](http://netcurl.org). The documentation of the
netcurl project can be furthermore be found at [docs.tornevall.net](https://docs.tornevall.net/display/TORNEVALL/NETCURLv6.1).
Note: When using composer, the actual project lies within github [here](https://github.com/Tornevall/tornelib-php-netcurl)
to maintain stability and reachability for deployers.

# netcurl-download

This is a very simple way of just downloading data top down from a file. However, some manifests includes
multiple content, which forces us to put and merge files into multiple content. This is a backside when
you for example using a playlist based on the [m3u](https://en.wikipedia.org/wiki/M3U) format. Many years from
now, m3u playlists was written as a local playlist for mp3 files. But for m3u8 files it has been a common way
to stream multiple files, for example movies - as the wikipedia also states.

The project is used as an educational example of how to simplify manual downloads of a playlist.
It has been created after several discussions on various forums where suggestions are either splitted up
on multiple discussion threads, or examples are  not available at all. The idea of this project came up
when I realized that I have my own library that actually handles network communication and uses best
available network driver to solve the problem. There are no data protection circumventions in this project.

The project example is in a single part based on the m3u format, *if* content based on m3u in the playlist
are are discovered (the DISCONTINUITY field that separates more file segments). However, it is used to
show how the netcurl library works, where you don't have to think about setting up the driver yourself, at all.


## The first idea

There was an initial idea around to run curl through a script like this:

    curl -s <url> >manifest.file
    cat -s manifest.file |grep -v ^#| awk '{system("curl -sS <extraUrlData>"$1 " >>merge.file")}'    

This is actually the easiest way of just downloading data. However, playlists may contain multiple
content that has to be split up into own files and this is where the journey began.

## How do I test this?

Well, first of all - add a composer script containing the library itself:

    {
        "require": {
            "tornevall/tornelib-php-netcurl": "^6.1"
        }
    }

In this sample project, all dependencies already exists, so that you can use "composer install" instantly. There
is also an example present, on how the netcurl library is used as a downloader class, but basically you could either
use the curl wrapper or the auto selective wrapper. In this example, the auto selective wrapper is chosen
since curl may not be the standard library on a system (note: if curl is not present, the driver will fall back
on the streams wrapper in PHP and use a binary safe method to download).

## Testing with the bundled files

You should start with the download.php file in the root path and follow the method calls, to see how it is built.
I call this "reverse engineering". You are however not alone. Netcurl comes with a documentation at
[https://docs.tornevall.net/display/TORNEVALL/NETCURLv6.1](docs.tornevall.net). You could also build, and adapt it for
your own needs, by yourself with your only bare hands as a tool - by calling the Wrappers, or do something like this:

    <?php
    require_once(__DIR__ . '/vendor/autoload.php');
    $download = new Playlist\Download();
    $download->setStoreDestination(__DIR__ . '/tmp');
    $download->setManifest('URL-to-playlist');
    $download->exec();
