# Wasabi
A Websocket loosely coupled tool for Prestashop carts, products &amp; combinations

[![Code Climate](https://codeclimate.com/github/absalomedia/wasabi/badges/gpa.svg)](https://codeclimate.com/github/absalomedia/wasabi) [![Codacy Badge](https://api.codacy.com/project/badge/b742fbd760e143b09b8bc1d3450bffc0)](https://www.codacy.com/app/media/wasabi) [![Build Status](https://scrutinizer-ci.com/g/absalomedia/wasabi/badges/build.png?b=master)](https://scrutinizer-ci.com/g/absalomedia/wasabi/build-status/master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/absalomedia/wasabi/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/absalomedia/wasabi/?branch=master)  [![SensioLabsInsight](https://insight.sensiolabs.com/projects/fac9d36c-ad9e-4fe0-b48f-7767aebcae48/mini.png)](https://insight.sensiolabs.com/projects/fac9d36c-ad9e-4fe0-b48f-7767aebcae48) [![HHVM Status](http://hhvm.h4cc.de/badge/abm/wasabi.svg)](http://hhvm.h4cc.de/package/abm/wasabi)

![Prestashop 1.5](https://img.shields.io/badge/Prestashop-1.5-blue.svg) ![Prestashop 1.6](https://img.shields.io/badge/Prestashop-1.6-red.svg)

## What's Wasabi?

Wasabi is a Websocket based tool, built using Composer &amp; Ratchet to expose data on a Prestashop ecommerce build. It's somewhat beta as well so expect gremlins but not dragons. Mostly tested with 1.5.6.x builds but also experimentally tested with 1.6.x. It's somewhat PSR-4 compliant as well.

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

Wasabi was originally designed to get product combination data from large scale / enterprise Prestashop ecommerce stores. It has since matured to allow cart & product data to also be captured via websockets.

As of the 0.1 release, there is 1 websocket server that can negotiate carts, products & combinations. How does it do this?

The basic code notation works as follows, and must be passed as a concatenated string (for obvious reasons):

```php
type|data as comma seperated variables
```

The various types available are:
 - cart
 - prod
 - comb

The 'cart' variable, used in finding related carts for a customer, requires the cart ID followed by the customer ID. Wasabi will return a JSON string with the other cart IDs that customer has along with the time they were created.

The 'prod' variable, used in finding basic product details via Websocket, operates two ways. When the first variable in the data string is not set to 0, it considers that variable the category & then looks in the specific category ID for that product. When that variable is set to 0, it ignores it & just looks at the second variable. In both cases, the second variable is always the product ID.  Wasabi will return a JSON string with the same standard variables as a Prestashop product.

The 'comb' variable allows you to find specific combination data, including pricing for a specific product. The data from this variable is used inside most Prestashop product.js files inside the findCombination(firstTime) loop. The first variable in the data stream for this setting is the product ID, followed by each individual combination variable IDs for that product (taken from the product Attributes & Values area). You need to refactor the findCombination(firstTime) function & the data in it to talk to the Wasabi websocket server before you can use it. Wasabi will return a JSON string with the same variables as the Prestashop combinations.
