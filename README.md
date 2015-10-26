# Wasabi
A Websocket loosely coupled tool for Prestashop carts, products &amp; combinations

[![Code Climate](https://codeclimate.com/github/absalomedia/wasabi/badges/gpa.svg)](https://codeclimate.com/github/absalomedia/wasabi) [![Codacy Badge](https://api.codacy.com/project/badge/b742fbd760e143b09b8bc1d3450bffc0)](https://www.codacy.com/app/media/wasabi) [![Build Status](https://travis-ci.org/absalomedia/wasabi.svg?branch=master)](https://travis-ci.org/absalomedia/wasabi) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/fac9d36c-ad9e-4fe0-b48f-7767aebcae48/mini.png)](https://insight.sensiolabs.com/projects/fac9d36c-ad9e-4fe0-b48f-7767aebcae48)

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
- Open port 8787 to 8789 on the internal firewall if you haven't already
- Profit


## Usage

Wasabi is aimed at product rendering process when dealing with massive amounts of combinations. This speeds up page load speed for products as the product combination data isn't rendered inline.

There is a function in most Prestashop themes inside product.js called

    findCombination(firstTime)

You need to refactor this function & the data in it to talk to the Wasabi websocket server before you can use it. Wasabi will return a JSON string with the same variables as the Prestashop combinations.
