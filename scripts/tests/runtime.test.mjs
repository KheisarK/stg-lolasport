import assert from "node:assert/strict";
import { test } from "node:test";
import { spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const php =
  process.env.PHP_BIN ||
  (existsSync("/workspace/lola-tools/bin/php")
    ? "/workspace/lola-tools/bin/php"
    : "php");

test("setup preflight accepts the installed runtime and lockfile extensions", () => {
  const result = spawnSync(php, ["scripts/check-runtime.php"], {
    cwd: root,
    encoding: "utf8",
  });
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /Runtime sesuai/);
});

test("missing extensions fail clearly, including fileinfo from the Windows CI failure", () => {
  const directory = mkdtempSync(path.join(tmpdir(), "lola-ini-test-"));
  try {
    const ini = path.join(directory, "php.ini");
    writeFileSync(ini, "; Isolated test: no dynamic extensions\n");
    const result = spawnSync(php, ["-c", ini, "scripts/check-runtime.php"], {
      cwd: root,
      encoding: "utf8",
      env: { ...process.env, PHP_INI_SCAN_DIR: "" },
    });
    assert.equal(result.status, 1, result.stderr);
    assert.match(result.stderr, /Aktifkan ekstensi:.*fileinfo/);
    assert.match(result.stderr, /php.ini aktif/);
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
});
