const { mix } = require('laravel-mix');
const paths = require('./tests/gulpconf.js');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

for(var key in paths){
    if(key == 'dist') continue;
    mix.styles(paths[key].css, paths.dist + 'css/'+ key +'.min.css');
    mix.scripts(paths[key].js, paths.dist + 'js/'+ key +'.min.js');
}
mix.copy('./public/vendor/layer/skin', paths.dist + 'js/skin');
mix.copy('./public/vendor/bootstrap/fonts', paths.dist + 'fonts');
mix.copy('./public/vendor/font-awesome/fonts', paths.dist + 'fonts');
