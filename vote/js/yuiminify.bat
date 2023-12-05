rem just the public pages...
rem https://github.com/yui/yuicompressor
java -jar yuicompressor-2.4.8.jar vote.js -o vote.min.js
java -jar yuicompressor-2.4.8.jar vote_manreg.js -o vote_manreg.min.js
java -jar yuicompressor-2.4.8.jar vote_common.js -o vote_common.min.js

