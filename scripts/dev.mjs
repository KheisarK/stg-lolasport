import { existsSync } from "node:fs";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import path from "node:path";
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const children = [];
let stopping = false;
function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) {
    try {
      if (process.platform !== "win32") process.kill(-child.pid, "SIGTERM");
      else child.kill("SIGTERM");
    } catch {
      /* Already exited. */
    }
  }
  setTimeout(() => process.exit(code), 300);
}
for (const [command, args, folder] of [
  [
    process.env.PHP_BIN ||
      (existsSync("/workspace/lola-tools/bin/php")
        ? "/workspace/lola-tools/bin/php"
        : "php"),
    [
      "artisan",
      "serve",
      "--no-reload",
      "--host=127.0.0.1",
      `--port=${process.env.BACKEND_PORT || 8000}`,
    ],
    "backend",
  ],
  [
    process.platform === "win32" ? "npm.cmd" : "npm",
    ["run", "dev", "--", "--port", process.env.FRONTEND_PORT || "3000"],
    "frontend",
  ],
]) {
  const child = spawn(command, args, {
    cwd: path.join(root, folder),
    stdio: "inherit",
    detached: process.platform !== "win32",
    env: {
      ...process.env,
      BACKEND_URL: `http://127.0.0.1:${process.env.BACKEND_PORT || 8000}`,
    },
  });
  children.push(child);
  child.on("error", (error) => {
    console.error(error.message);
    stop(1);
  });
  child.on("exit", (code) => {
    if (!stopping) stop(code || 1);
  });
}
process.on("SIGINT", () => stop());
process.on("SIGTERM", () => stop());
