var gulp = require('gulp'),
    apidoc = require('gulp-apidoc'),
    watch = require('gulp-watch')
;

var buildApiDoc = function(done) {
    apidoc({
        src: "src/",
        dest: "doc/",
        config: "./",
        silent: true
    }, done);
};

gulp.task('apidoc', buildApiDoc);

gulp.task('watch', function () {
    watch('src/**/*.php', function() {
        buildApiDoc();
    });
});