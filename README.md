# Wasabi
A Websocket loosely coupled tool for Prestashop products &amp; combinations

## What's Wasabi?

Wasabi is a Websocket based tool, built using Composer &amp; Ratchet to expose data on a Prestashop ecommerce build. It's very alpha as well so expect gremlins but not dragons. Mostly tested with 1.5.x builds but also experimentally tested with 1.6.x. It's somewhat PSR-4 compliant as well.

## Installation

The easiest path to install is:

- Download this repo or git clone it
- Place into relevant Prestashop site in a subdirectory
- Run Composer in that subdirectory

	composer install

- Watch as it builds in all the dependencies
- Run a background cron job to keep the index.php file running as a server
- Open port 8787 on the internal firewall if you haven't already
- Profit 


## Usage

Wasabi is aimed at product rendering process when dealing with massive amounts of combinations. This speeds up page load speed for products as the product combination data isn't rendered inline. 

There is a function in most Prestashop themes inside product.js called

	findCombination(firstTime)

You need to refactor this proces to talk to the Wasabi websocket server before you can use it. Wabasi will return a JSON string with the same variables as the Prestashop combinations.