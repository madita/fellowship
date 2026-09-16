#!/usr/bin/env node
/*
  Cross-ecosystem dependency audit script
  - npm (package-lock.json / package.json)
  - yarn (yarn.lock)
  - composer (composer.lock / composer.json)

  Outputs: dependency-audit-<YYYY-MM-DD>.md in repo root
*/

const { execSync, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const repoRoot = process.cwd();

function hasFile(file) {
  return fs.existsSync(path.join(repoRoot, file));
}

function run(cmd, args = [], opts = {}) {
  try {
    const res = spawnSync(cmd, args, { encoding: 'utf8', cwd: repoRoot, ...opts });
    if (res.error) throw res.error;
    const code = res.status === null ? res.exitCode : res.status;
    if (code !== 0 && !(opts.allowNonZero)) {
      const err = new Error(`Command failed: ${cmd} ${args.join(' ')}\n${res.stderr || res.stdout}`);
      err.stdout = res.stdout;
      err.stderr = res.stderr;
      err.code = code;
      throw err;
    }
    return { stdout: res.stdout || '', stderr: res.stderr || '', code: code || 0 };
  } catch (e) {
    return { error: e, stdout: e.stdout || '', stderr: e.stderr || '', code: e.code || 1 };
  }
}

function parseJsonSafe(s) {
  try { return JSON.parse(s); } catch { return null; }
}

function todayStr() {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${yyyy}-${mm}-${dd}`;
}

// Collect audits per ecosystem
const report = {
  generatedAt: new Date().toISOString(),
  ecosystems: [],
  notes: []
};

function addEco(name) {
  const eco = { name, tools: {}, vulnerabilities: [], updates: [], errors: [] };
  report.ecosystems.push(eco);
  return eco;
}

// npm
if (hasFile('package.json')) {
  const eco = addEco('npm');
  // audit
  const auditRes = run('npm', ['audit', '--json'], { allowNonZero: true });
  if (auditRes.error && auditRes.stderr.includes('ENOENT')) {
    eco.errors.push('npm not available in PATH.');
  } else {
    const auditJson = parseJsonSafe(auditRes.stdout);
    if (auditJson) {
      // npm v7+ format: vulnerabilities listed in "vulnerabilities" and advisories in "advisories" (older)
      if (auditJson.vulnerabilities) {
        for (const [pkg, v] of Object.entries(auditJson.vulnerabilities)) {
          // Try to extract ids and fix version
          const via = (v.via || []).map(x => typeof x === 'string' ? { title: x } : x);
          via.forEach(ad => {
            eco.vulnerabilities.push({
              package: pkg,
              ecosystem: 'npm',
              severity: ad.severity || v.severity || 'unknown',
              id: ad.source || ad.url || ad.name || ad.title || 'N/A',
              title: ad.title || ad.name || pkg,
              url: ad.url || undefined,
              range: ad.range || v.range || undefined,
              current: v.installedVersion || undefined,
              fixAvailable: v.fixAvailable || undefined,
              patched_version: (ad.effects && ad.effects.fixed) || undefined
            });
          });
        }
      } else if (auditJson.advisories) {
        for (const adv of Object.values(auditJson.advisories)) {
          eco.vulnerabilities.push({
            package: adv.module_name,
            ecosystem: 'npm',
            severity: adv.severity,
            id: adv.cves && adv.cves.length ? adv.cves.join(',') : (adv.github_advisory_id || adv.id),
            title: adv.title,
            url: adv.url,
            range: adv.vulnerable_versions,
            current: adv.findings && adv.findings[0] && adv.findings[0].version,
            fixAvailable: adv.patched_versions && adv.patched_versions !== '<0.0.0',
            patched_version: adv.patched_versions
          });
        }
      } else {
        eco.notes.push('npm audit returned no vulnerabilities object.');
      }
    } else {
      eco.errors.push('Failed to parse npm audit JSON.');
    }
  }
  // outdated
  const outdatedRes = run('npm', ['outdated', '--json'], { allowNonZero: true });
  const outdatedJson = parseJsonSafe(outdatedRes.stdout);
  if (outdatedJson) {
    for (const [pkg, info] of Object.entries(outdatedJson)) {
      eco.updates.push({
        package: pkg,
        current: info.current,
        wanted: info.wanted,
        latest: info.latest,
        type: info.type || undefined,
        level: semverDiff(info.current, info.wanted, info.latest)
      });
    }
  } else if (outdatedRes.stdout.trim() === '') {
    // no updates
  } else {
    eco.errors.push('Failed to parse npm outdated output.');
  }
}

// yarn
if (hasFile('yarn.lock')) {
  const eco = addEco('yarn');
  // yarn audit (v1) emits NDJSON lines
  let yarnAudit = run('yarn', ['audit', '--json'], { allowNonZero: true });
  if (yarnAudit.error && /Unknown syntax|Command not found|Unknown command/.test(yarnAudit.stderr || yarnAudit.stdout)) {
    // try modern yarn
    yarnAudit = run('yarn', ['npm', 'audit', '--json'], { allowNonZero: true });
  }
  if (!yarnAudit.error) {
    const vulns = [];
    for (const line of (yarnAudit.stdout || '').split(/\r?\n/)) {
      if (!line.trim()) continue;
      const obj = parseJsonSafe(line);
      if (!obj) continue;
      if (obj.type === 'auditAdvisory' && obj.data && obj.data.advisory) {
        const a = obj.data.advisory;
        vulns.push({
          package: a.module_name,
          ecosystem: 'yarn',
          severity: a.severity,
          id: (a.cves && a.cves[0]) || a.github_advisory_id || a.id,
          title: a.title,
          url: a.url,
          range: a.vulnerable_versions,
          current: a.findings && a.findings[0] && a.findings[0].version,
          fixAvailable: a.patched_versions && a.patched_versions !== '<0.0.0',
          patched_version: a.patched_versions
        });
      }
    }
    eco.vulnerabilities.push(...vulns);
  } else {
    eco.errors.push('Failed to run yarn audit.');
  }
  // yarn outdated
  let yarnOut = run('yarn', ['outdated', '--json'], { allowNonZero: true });
  const updates = {};
  for (const line of (yarnOut.stdout || '').split(/\r?\n/)) {
    const obj = parseJsonSafe(line);
    if (!obj) continue;
    if (obj.type === 'table' && obj.data && obj.data.body) {
      for (const row of obj.data.body) {
        // columns: Package, Current, Wanted, Latest, Package Type, URL
        const [pkg, current, wanted, latest, type] = row;
        updates[pkg] = { current, wanted, latest, type };
      }
    }
  }
  for (const [pkg, info] of Object.entries(updates)) {
    eco.updates.push({
      package: pkg,
      current: info.current,
      wanted: info.wanted,
      latest: info.latest,
      type: info.type,
      level: semverDiff(info.current, info.wanted, info.latest)
    });
  }
}

// composer
if (hasFile('composer.json')) {
  const eco = addEco('composer');
  // composer audit
  let compAudit = run('composer', ['audit', '--format=json'], { allowNonZero: true });
  if (compAudit.error && /Command "audit" is not defined/.test(compAudit.stderr || '')) {
    eco.notes.push('composer audit not supported by your Composer version.');
  } else {
    const compJson = parseJsonSafe(compAudit.stdout);
    if (compJson && compJson.audit) {
      const advisories = compJson.audit.advisories || [];
      for (const adv of advisories) {
        // Composer advisories format may vary
        const sources = adv.sources || {};
        const id = (sources['cve'] && sources['cve'][0]) || adv.id || adv.title;
        eco.vulnerabilities.push({
          package: adv.package_name || adv.package || 'unknown',
          ecosystem: 'composer',
          severity: adv.cve_severity || adv.severity || 'unknown',
          id,
          title: adv.title || adv.link || 'Advisory',
          url: adv.link || (adv.sources && adv.sources.link) || undefined,
          range: adv.affected_versions || adv.version_constraint || undefined,
          current: undefined,
          fixAvailable: adv.reported_at ? true : undefined,
          patched_version: adv.cve_fixed_in || adv.suggested_update || undefined
        });
      }
    } else if (compJson && compJson.problems) {
      eco.notes.push('Composer audit reported no vulnerabilities.');
    } else if (!compAudit.error) {
      eco.notes.push('Composer audit produced unexpected output.');
    } else {
      eco.errors.push('Failed to run composer audit.');
    }
  }
  // composer outdated
  let compOut = run('composer', ['outdated', '--format=json'], { allowNonZero: true });
  let compOutJson = parseJsonSafe(compOut.stdout);
  if (!compOutJson) {
    // Fallback older composer
    compOut = run('composer', ['show', '-l', '--outdated', '--direct', '--format=json'], { allowNonZero: true });
    compOutJson = parseJsonSafe(compOut.stdout);
  }
  if (compOutJson && (compOutJson.installed || compOutJson.locked || compOutJson.outdated)) {
    const list = compOutJson.installed || compOutJson.locked || compOutJson.outdated || [];
    for (const pkg of list) {
      const current = pkg.version || pkg['version_normalized'] || pkg['version-installed'] || pkg['version'] || 'unknown';
      const latest = pkg.latest || pkg['latest-status'] === 'semver-safe-update' ? pkg['latest'] : (pkg.latest || pkg['latest'] || pkg['latest'] );
      const wanted = pkg['latest'] || latest;
      eco.updates.push({
        package: pkg.name,
        current,
        wanted,
        latest,
        type: 'direct',
        level: semverDiff(current, wanted, latest)
      });
    }
  } else if (compOut.stdout.trim() === '') {
    // none
  } else {
    eco.notes.push('Composer outdated not available or unrecognized format.');
  }
}

function isValidSemver(v) {
  return /^v?\d+\.\d+\.\d+(-[0-9A-Za-z-.]+)?$/.test(v || '');
}

function semverParse(v) {
  if (!v) return null;
  const m = /^v?(\d+)\.(\d+)\.(\d+)/.exec(v);
  if (!m) return null;
  return { major: +m[1], minor: +m[2], patch: +m[3] };
}

function semverCmp(a, b) {
  const pa = semverParse(a); const pb = semverParse(b);
  if (!pa || !pb) return 0;
  if (pa.major !== pb.major) return pa.major - pb.major;
  if (pa.minor !== pb.minor) return pa.minor - pb.minor;
  return pa.patch - pb.patch;
}

function semverDiff(current, wanted, latest) {
  const from = semverParse(current);
  const to = semverParse(wanted || latest);
  if (!from || !to) return 'unknown';
  if (to.major > from.major) return 'major';
  if (to.minor > from.minor) return 'minor';
  if (to.patch > from.patch) return 'patch';
  return 'none';
}

function groupBy(arr, key) {
  const map = new Map();
  for (const item of arr) {
    const k = item[key] || 'unknown';
    if (!map.has(k)) map.set(k, []);
    map.get(k).push(item);
  }
  return map;
}

function mdEscape(s) {
  return String(s || '').replace(/\|/g, '\\|');
}

function generateMarkdown(rep) {
  const date = todayStr();
  let out = '';
  out += `# Dependency Audit ${date}\n\n`;
  out += `Generated at: ${rep.generatedAt}\n\n`;
  if (rep.notes && rep.notes.length) {
    out += `Notes:\n`;
    for (const n of rep.notes) out += `- ${n}\n`;
    out += `\n`;
  }
  for (const eco of rep.ecosystems) {
    out += `## ${eco.name}\n\n`;
    if (eco.errors.length) {
      out += `Errors:\n`;
      for (const e of eco.errors) out += `- ${e}\n`;
      out += `\n`;
    }
    // Vulnerabilities
    out += `### Vulnerabilities\n\n`;
    if (!eco.vulnerabilities.length) {
      out += `No known vulnerabilities reported by tool.\n\n`;
    } else {
      out += `| Package | Severity | ID | Title | Affected | Fixed In | Link |\n`;
      out += `|---|---|---|---|---|---|---|\n`;
      for (const v of eco.vulnerabilities) {
        out += `| ${mdEscape(v.package)} | ${mdEscape(v.severity)} | ${mdEscape(v.id)} | ${mdEscape(v.title)} | ${mdEscape(v.range || '')} | ${mdEscape(v.patched_version || (v.fixAvailable && typeof v.fixAvailable === 'object' && v.fixAvailable.version) || '')} | ${mdEscape(v.url || '')} |\n`;
      }
      out += `\n`;
    }
    // Updates
    const majors = eco.updates.filter(u => u.level === 'major');
    const minors = eco.updates.filter(u => u.level === 'minor');
    const patches = eco.updates.filter(u => u.level === 'patch');

    out += `### Updates Available\n\n`;
    if (!eco.updates.length) {
      out += `All dependencies up-to-date.\n\n`;
    } else {
      out += `Minor/Patch updates (safe to apply):\n\n`;
      const safe = [...patches, ...minors];
      if (safe.length) {
        out += `| Package | Current | Wanted | Latest | Level |\n`;
        out += `|---|---|---|---|---|\n`;
        for (const u of safe) {
          out += `| ${mdEscape(u.package)} | ${mdEscape(u.current)} | ${mdEscape(u.wanted)} | ${mdEscape(u.latest)} | ${mdEscape(u.level)} |\n`;
        }
        out += `\n`;
      } else {
        out += `No minor/patch updates available.\n\n`;
      }
      out += `Major updates (batched; potential breaking changes):\n\n`;
      if (majors.length) {
        out += `| Package | Current | Latest | Note |\n`;
        out += `|---|---|---|---|\n`;
        for (const u of majors) {
          out += `| ${mdEscape(u.package)} | ${mdEscape(u.current)} | ${mdEscape(u.latest)} | Potential breaking changes; review changelog. |\n`;
        }
        out += `\n`;
      } else {
        out += `No major updates available.\n\n`;
      }
    }
  }
  out += `---\n\n`;
  out += `Next step: I can apply patch/minor updates and run the test suites (JS and PHP) to ensure everything passes. Would you like me to proceed with safe updates?\n`;
  return out;
}

// Execute generation
const md = generateMarkdown(report);
const filename = path.join(repoRoot, `dependency-audit-${todayStr()}.md`);
try {
  fs.writeFileSync(filename, md, 'utf8');
  console.log(`Wrote ${filename}`);
} catch (e) {
  console.error('Failed to write audit file:', e.message);
  process.exitCode = 1;
}
