import { spawn } from "node:child_process";
import { existsSync } from "node:fs";
import path from "node:path";

// Windows cannot spawn npm.cmd directly. Run npm's JavaScript entry point
// with Node, keeping arguments and paths out of a command shell.
export function npmInvocation(
  args,
  {
    platform = process.platform,
    execPath = process.execPath,
    env = process.env,
    exists = existsSync,
  } = {},
) {
  if (platform !== "win32") return ["npm", args];
  const candidates = [
    env.npm_execpath,
    path.win32.join(
      path.win32.dirname(execPath),
      "node_modules/npm/bin/npm-cli.js",
    ),
    ...(env.Path || env.PATH || "")
      .split(";")
      .filter(Boolean)
      .map((directory) =>
        path.win32.join(directory, "node_modules/npm/bin/npm-cli.js"),
      ),
  ];
  const cli = candidates.find(
    (file) =>
      file && path.win32.basename(file) === "npm-cli.js" && exists(file),
  );
  if (!cli) {
    throw new Error(
      "File npm-cli.js tidak ditemukan. Pasang Node.js dengan npm, lalu buka ulang terminal VS Code.",
    );
  }
  return [execPath, [cli, ...args]];
}

export async function stopService(child) {
  if (!child.pid || child.exitCode !== null) return;
  if (process.platform === "win32") {
    // npm and Artisan start child processes; stopping only the parent leaves
    // Next.js/PHP holding their ports on Windows.
    await new Promise((resolve) => {
      const killer = spawn(
        "taskkill.exe",
        ["/PID", String(child.pid), "/T", "/F"],
        {
          stdio: "ignore",
          windowsHide: true,
        },
      );
      killer.once("error", () => {
        child.kill("SIGTERM");
        resolve();
      });
      killer.once("exit", resolve);
    });
  } else {
    try {
      process.kill(-child.pid, "SIGTERM");
    } catch {
      // The task's process group already exited.
    }
  }
}
