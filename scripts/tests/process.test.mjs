import assert from "node:assert/strict";
import { test } from "node:test";
import { spawnSync } from "node:child_process";
import { mkdtemp, writeFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import path from "node:path";
import { npmInvocation } from "../process.mjs";

test("Windows uses Node's npm entry point even when npm.cmd exists", () => {
  const cli = "C:\\Program Files\\nodejs\\node_modules\\npm\\bin\\npm-cli.js";
  const [command, args] = npmInvocation(["run", "dev"], {
    platform: "win32",
    execPath: "C:\\Program Files\\nodejs\\node.exe",
    env: {},
    exists: (file) => file === cli || file.endsWith("npm.cmd"),
  });
  assert.equal(command, "C:\\Program Files\\nodejs\\node.exe");
  assert.deepEqual(args, [cli, "run", "dev"]);
});

test("Windows finds a separate npm installation on PATH", () => {
  const cli =
    "C:\\Users\\Kheisar K\\AppData\\Roaming\\npm\\node_modules\\npm\\bin\\npm-cli.js";
  const [, args] = npmInvocation(["ci"], {
    platform: "win32",
    execPath: "C:\\Tools\\node\\node.exe",
    env: {
      Path: "C:\\Windows\\System32;C:\\Users\\Kheisar K\\AppData\\Roaming\\npm",
    },
    exists: (file) => file === cli,
  });
  assert.deepEqual(args, [cli, "ci"]);
});

test("Missing Windows npm gives an actionable error", () => {
  assert.throws(
    () =>
      npmInvocation([], {
        platform: "win32",
        execPath: "C:\\node.exe",
        env: {},
        exists: () => false,
      }),
    /npm-cli.js tidak ditemukan/,
  );
});

test("Direct npm launch preserves spaces, backticks and literal arguments", async () => {
  const directory = await mkdtemp(path.join(tmpdir(), "lola npm ` path "));
  try {
    const cli = path.join(directory, "npm-cli.js");
    await writeFile(
      cli,
      "console.log(JSON.stringify({ args: process.argv.slice(2), cwd: process.cwd() }));\n",
    );
    const input = [
      "run",
      "dev",
      "--",
      "space value",
      "`literal`",
      "&literal",
      "%PATH%",
    ];
    const [command, args] = npmInvocation(input, {
      platform: "win32",
      execPath: process.execPath,
      env: { npm_execpath: cli },
    });
    const result = spawnSync(command, args, {
      cwd: directory,
      encoding: "utf8",
    });
    assert.equal(result.status, 0, result.stderr);
    const output = JSON.parse(result.stdout);
    assert.deepEqual(output.args, input);
    assert.equal(output.cwd, directory);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test("Non-Windows keeps npm's normal executable invocation", () => {
  assert.deepEqual(npmInvocation(["ci"], { platform: "linux" }), [
    "npm",
    ["ci"],
  ]);
});
