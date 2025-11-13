import json
import sys
from pathlib import Path
from datetime import datetime, date

import pandas as pd
import matplotlib.pyplot as plt
from matplotlib.backends.backend_pdf import PdfPages

from datetime import date

# -------------------
# Load HAR
# -------------------
def load_har(path):
    with open(path, "r", encoding="utf-8") as f:
        har = json.load(f)

    entries = har["log"]["entries"]
    rows = []

    for e in entries:
        req = e.get("request", {})
        res = e.get("response", {})
        started = e.get("startedDateTime")
        mime = res.get("content", {}).get("mimeType", "")
        size = res.get("bodySize", 0)
        if size is None or size < 0:
            size = 0

        ua = ""
        for h in req.get("headers", []):
            if h.get("name", "").lower() == "user-agent":
                ua = h.get("value", "")
                break

        rows.append({
            "time": started,
            "method": req.get("method"),
            "url": req.get("url"),
            "status": res.get("status"),
            "mime_type": mime,
            "bytes": size,
            "user_agent": ua
        })

    df = pd.DataFrame(rows)

    if not df.empty:
        df["time"] = pd.to_datetime(df["time"], errors="coerce")

    return df


# -------------------
# CSV GENERATION
# -------------------
def make_requests_csv(df, outdir):
    df.to_csv(outdir / "requests.csv", index=False)


def make_pages_csv(df, outdir):
    from urllib.parse import urlparse
    df2 = df.copy()
    df2["path"] = df2["url"].apply(lambda u: urlparse(u).path if isinstance(u, str) else "")
    pages = df2.groupby("path").size().reset_index(name="hits")
    pages.sort_values("hits", ascending=False).to_csv(outdir / "pages.csv", index=False)


def make_status_csv(df, outdir):
    df.groupby("status").size().reset_index(name="count") \
      .to_csv(outdir / "status_codes.csv", index=False)


# -------------------
# TIMELINE FOR TODAY
# -------------------

def timeline_today(df):
    # Drop rows without a valid timestamp
    df_valid = df.dropna(subset=["time"]).copy()
    if df_valid.empty:
        print("No valid timestamps at all.")
        return None

    # Derive just the calendar date from the timestamp
    # This automatically handles timezone internally.
    df_valid["date"] = df_valid["time"].dt.date

    today = date.today()

    # Keep only requests whose LOCAL calendar date == today
    df_today = df_valid[df_valid["date"] == today].copy()
    if df_today.empty:
        print("No requests with today's date; skipping PDF.")
        return None

    # Group by hour for today
    df_today["hour"] = df_today["time"].dt.floor("H")
    df_today["is_error"] = df_today["status"].astype("Int64") >= 400

    agg = df_today.groupby("hour").agg(
        total_requests=("url", "count"),
        error_requests=("is_error", "sum"),
    ).reset_index()

    return agg



# -------------------
# PDF (today only)
# -------------------
def make_pdf_today(timeline, outdir):
    if timeline is None or timeline.empty:
        print("No requests today → skipping PDF.")
        return

    pdf_path = outdir / "report_today.pdf"
    with PdfPages(pdf_path) as pdf:

        plt.figure()
        plt.plot(timeline["hour"], timeline["total_requests"], marker="o")
        plt.title("Requests per Hour (Today)")
        plt.xlabel("Hour")
        plt.ylabel("Total Requests")
        plt.xticks(rotation=45)
        plt.tight_layout()
        pdf.savefig()
        plt.close()

        plt.figure()
        plt.plot(timeline["hour"], timeline["error_requests"], color="red", marker="o")
        plt.title("Error Requests per Hour (Today)")
        plt.xlabel("Hour")
        plt.ylabel("Errors (status ≥ 400)")
        plt.xticks(rotation=45)
        plt.tight_layout()
        pdf.savefig()
        plt.close()

    print(f"PDF written to: {pdf_path}")


# -------------------
# MAIN
# -------------------
def main():
    if len(sys.argv) < 2:
        print("Usage: python analyze_har_today.py logs.har [outdir]")
        sys.exit(1)

    har_path = Path(sys.argv[1])
    outdir = Path(sys.argv[2]) if len(sys.argv) >= 3 else Path("har_out_today")
    outdir.mkdir(parents=True, exist_ok=True)

    df = load_har(har_path)
    print(f"Loaded {len(df)} HAR entries.")

    make_requests_csv(df, outdir)
    make_pages_csv(df, outdir)
    make_status_csv(df, outdir)

    tl = timeline_today(df)
    make_pdf_today(tl, outdir)

    print(f"All CSVs written to: {outdir.resolve()}")


if __name__ == "__main__":
    main()
