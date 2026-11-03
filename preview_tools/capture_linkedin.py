from pathlib import Path
import argparse
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT.parent / "SURGERY-APP-preview-runtime"
sys.path.insert(0, str(RUNTIME / "python-packages"))

from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser(description="Capture the six demo roles for LinkedIn.")
parser.add_argument("--scale", type=float, default=1.5, help="Pixel scale; 3 gives 4800x3000 PNGs.")
parser.add_argument("--output", default="linkedin_screenshots", help="Output folder inside the clone.")
args = parser.parse_args()
if not 1 <= args.scale <= 4:
    parser.error("--scale must be between 1 and 4")
BASE = "http://127.0.0.1:8000"
OUTPUT = ROOT / args.output
OUTPUT.mkdir(exist_ok=True)

CASES = [
    ("02_surgeon_snomed_lookup.png", "Surgeon", "/views/surgeon/operative_report.php?id=173", "snomed"),
    ("03_nurse_safety_cockpit.png", "Nurse", "/views/nurse/surgery_cockpit.php?spid=173", None),
    ("04_anesthesiologist_cockpit.png", "Anesthesiologist", "/views/anesthesiologist/live_cockpit.php?id=900001", "anesthesia"),
    ("05_patient_portal.png", "Patient", "/views/patient/patient_dashboard.php", None),
    ("06_patient_surgery_details.png", "Patient", "/views/patient/surgery_details.php?id=119", None),
    ("07_administrator_scheduler.png", "Administrator", "/views/admin/admin_scheduler_advanced.php", None),
    ("08_director_inventory.png", "Director", "/views/director/inventory.php", None),
]

def login(page, role):
    response = page.goto(BASE + "/login.php", wait_until="domcontentloaded")
    if response is None or response.status != 200:
        raise RuntimeError(f"Login page did not load for {role}")
    page.locator(".user-chip").filter(has_text=re.compile(role, re.I)).first.click()
    page.get_by_role("button", name="Sign In").click()
    page.wait_for_load_state("domcontentloaded")
    if "data_agreement.php" in page.url:
        page.get_by_role("button", name="Accept & Continue").click()
        page.wait_for_load_state("domcontentloaded")
    if "login.php" in page.url:
        raise RuntimeError(f"Demo login failed for {role}")

def check_page(page, filename):
    body = page.locator("body").inner_text()
    if re.search(r"Fatal error|Access Denied|Notice:|Error de conexión", body, re.I):
        raise RuntimeError(f"PHP error in {filename}: {body[:160]}")
    if len(body) < 250:
        raise RuntimeError(f"Suspiciously empty page in {filename}: {body[:160]}")

with sync_playwright() as p:
    chrome = Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe")
    edge = Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")
    browser_path = chrome if chrome.exists() else edge
    if not browser_path.exists():
        raise RuntimeError("Chrome or Edge is required")
    browser = p.chromium.launch(executable_path=str(browser_path), headless=True)
    context = browser.new_context(viewport={"width": 1600, "height": 1000}, device_scale_factor=args.scale)
    page = context.new_page()
    page.goto(BASE + "/login.php", wait_until="domcontentloaded")
    page.wait_for_timeout(1000)
    check_page(page, "01_login.png")
    page.screenshot(path=str(OUTPUT / "01_login.png"), animations="disabled")
    context.close()

    for filename, role, route, action in CASES:
        context = browser.new_context(viewport={"width": 1600, "height": 1000}, device_scale_factor=args.scale)
        page = context.new_page()
        login(page, role)
        response = page.goto(BASE + route, wait_until="domcontentloaded")
        if response is None or response.status != 200:
            raise RuntimeError(f"Page did not load: {filename}")
        page.wait_for_timeout(1200)
        if action == "anesthesia":
            page.wait_for_function("document.querySelector('#disp_hr')?.textContent.trim() !== '--'", timeout=5000)
            data = page.evaluate("""async () => (await fetch('/views/anesthesiologist/api_anesthesia.php?spid=900001&action=get_data')).json()""")
            if not data.get("success") or len(data.get("chart_data", [])) < 10:
                raise RuntimeError("Synthetic anesthesia chart is not populated")
            if "SYNTHETIC DEMO" not in page.locator("body").inner_text():
                raise RuntimeError("Anesthesia demo label is missing")
        if action == "snomed":
            page.locator("#snomed_search").fill("osteo")
            page.wait_for_timeout(1200)
            if page.locator(".snomed-item:visible").count() < 1:
                raise RuntimeError("SNOMED demo lookup returned no visible results")
        check_page(page, filename)
        page.screenshot(path=str(OUTPUT / filename), animations="disabled")
        print(filename, page.title())
        context.close()
    browser.close()
print("Saved", len(CASES) + 1, "PNG files to", OUTPUT)
