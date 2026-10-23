<?php

if (!function_exists('embed_image')) {

    /**
     * Given a file which resides in resources/img, encode it as base64 and prefix it
     * so that it can be used in an img tag or similar within an email, without having to
     * worry about attaching it as an attachment.
     *
     * @param string $filename Filename to embed
     * @return string base64 encoded image file
     */
    function embed_image(string $filename): string
    {
        return sprintf("data:image/png;base64,%s", base64_encode(
            file_get_contents(
                sprintf("%s/img/%s", resource_path(), $filename)
            )
        ));
    }
}