import os, sys
os.chdir('/Users/hafirahman/Downloads/trumanenterprisenarrowboattrust/website')
port = int(os.environ.get('PORT', 3000))
import http.server, socketserver
handler = http.server.SimpleHTTPRequestHandler
with socketserver.TCPServer(("", port), handler) as httpd:
    print(f"Serving on port {port}", flush=True)
    httpd.serve_forever()
