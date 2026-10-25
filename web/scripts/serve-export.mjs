// Dependency-free local preview of the static export.
import { createServer } from "node:http";
import { readFile, stat } from "node:fs/promises";
import { extname, resolve, sep } from "node:path";
const root = resolve("out");
const types = {
  ".html": "text/html",
  ".js": "text/javascript",
  ".css": "text/css",
  ".svg": "image/svg+xml",
  ".xml": "application/xml",
  ".txt": "text/plain",
  ".json": "application/json",
};
createServer(async (req, res) => {
  try {
    const pathname = decodeURIComponent(
      new URL(req.url, "http://localhost").pathname,
    );
    let path = resolve(root, `.${pathname}`);
    if (path !== root && !path.startsWith(root + sep)) {
      res.writeHead(403).end();
      return;
    }
    if ((await stat(path)).isDirectory()) path = resolve(path, "index.html");
    const body = await readFile(path);
    res
      .writeHead(200, {
        "Content-Type": `${types[extname(path)] || "application/octet-stream"}; charset=utf-8`,
      })
      .end(body);
  } catch {
    res
      .writeHead(404, { "Content-Type": "text/html" })
      .end(await readFile(resolve(root, "404.html")));
  }
}).listen(3000, "0.0.0.0", () =>
  console.log("CareConnect static preview: http://localhost:3000"),
);
