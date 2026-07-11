# Shared security headers. Include in http{} and in any location that sets
# its own add_header (nginx drops inherited ones otherwise). Not named
# *.conf so conf.d/*.conf globbing doesn't load it twice.
add_header X-Frame-Options        "SAMEORIGIN"                      always;
add_header X-Content-Type-Options "nosniff"                         always;
add_header Referrer-Policy        "strict-origin-when-cross-origin" always;
