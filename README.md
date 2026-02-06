# icros
*Image processing host application optimized for fast serves and minimal PHP proxying*

### How it works
  
  **icros** is a simple PHP proxy file and an Nginx configuration that handles special query parameters to resize and compress images before serving them.
  
  By leveraging Nginx's `try_files` directive, it avoids passing through the PHP proxy more than once after an image has been processed, by telling Nginx to look for the already-stored redirect image directly on the save path.
  
### How to install

- Install the icros repository on the root of the domain you'd like to serve *(eg.: `/home/forge/static.myapp.com`)*
- Copy the `.env.example` file to `.env`, and configure it accordingly:

	Key | Description
    --- | --- 
    APP_ENV | The environment which the app is running; unused for now
    APP_DEBUG | Activate debug mode? Set this to false to never report errors to the user
     APP_URL | The base URL of the host
     FETCH_DRIVER | Which driver to use when fetching files; right now, only `local` is available
     FETCH_PATH | Where to fetch master files from; this can be any absolute path
     STORE_PATH | Where to store processed images; this should be relative to the application root (`index.php`). If you require this path to be elsewhere, make sure to update `nginx_site.conf` accordingly.

- Add the `nginx_site.conf` file to your `/etc/nginx/sites_enabled` folder, making sure to setup `server_name` and `root` variables accordingly.
  
  If you already have an existing `.conf` file and would like to preserve it, all you have to do is swap your `location /` section to the following:
  
  ```
  location / {
    try_files $uri@$query_string /index.php?$query_string;
  }
  ```
  
- Restart your Nginx
- Presto!

### How to use

After installing, your images will be served through the domain you configured.

	http://static.myapp.com/lorem/ipsum/dolor.jpg
	
To add processing parameters to your image, you add a series of options as query parameters:

	http://static.myapp.com/lorem/ipsum/dolor.jpg?mCROP,w300,h400.jpg
	
The URL above will crop the image by 300x400, and store/respond it as JPG. If you omit the extension, the image will be served as JPEG.

Supported image formats: JPEG, PNG, GIF, and WebP.

The following options are available:

Prefix | Option | Description
--- | --- | ---
m | Resize mode | Resize mode to use. Available modes: `RESIZE`, `CROP`, `COVER`, `ORIGINAL`.
w | Target width | The desired width of the image, in `px`.
h | Target height | The desired height of the image, in `px`.
x | Crop X | If cropping, the desired X coordinate of the starting point for the crop, in `px`.
y | Crop Y | If cropping, the desired Y coordinate of the starting point for the crop, in `px`.
q | JPEG quality | If processing as JPEG, the desired JPEG quality, from `10` to `100`.

The following resize modes are available:

Mode | Description
--- | ---
ORIGINAL | A no-op resize; will respond with the original image, not resized. This is the default handler when the `m` option is omitted.
CROP | Crop the image to the defined `width` and `height` rectangle frame. If `x` and `y` are specified, will move the starting position of the crop frame accordingly.
COVER | Similar to `background-size: cover` in CSS. Will attempt to fill the rectangle defined by `width` and `height` with most of the image (stretching the smallest dimension), just enough so the image fills the frame wholly.
RESIZE | Scales the image exactly to the `width` and `height` specified, not preserving aspect ratio.

### Feature backlog

- Support to transformation chaining (by using the `|` operator)
- Support additional transformer options (like `blur`, `lighter`, `darken`, `blackwhite`, etc)
- Fix calls to the original image (without transformers) having to pass at least once through the PHP proxy (needs further investigation on Nginx's `try_files` and `rewrite` statements).

### License

MIT