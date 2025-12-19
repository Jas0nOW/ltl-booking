import json
import os
import re
import time
import urllib.parse
import urllib.request

import polib

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
LANG_DIR = os.path.join(BASE_DIR, "languages")
CACHE_DIR = os.path.join(BASE_DIR, "translations-cache")
TARGETS = {
    "de_DE": "de",
    "es_ES": "es",
}
PLACEHOLDER_PATTERN = re.compile(r"%(?:\d+\$)?[sd%]")
BASE_URL = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&dt=t"
BATCH_SIZE = 8

os.makedirs(CACHE_DIR, exist_ok=True)


def mask_placeholders(text):
    placeholders = []

    def replace(match):
        value = match.group(0)
        placeholders.append(value)
        return f"__PH_{len(placeholders) - 1}__"

    masked = PLACEHOLDER_PATTERN.sub(replace, text)
    return masked, placeholders


def restore_placeholders(text, placeholders):
    for index, value in enumerate(placeholders):
        text = text.replace(f"__PH_{index}__", value)
    return text


def translate_batch(masked_texts, target_lang):
    if not masked_texts:
        return []

    query = "".join(f"&tl={target_lang}&q={urllib.parse.quote(text)}" for text in masked_texts)
    url = f"{BASE_URL}{query}"

    for attempt in range(3):
        try:
            with urllib.request.urlopen(url, timeout=20) as response:
                payload = json.loads(response.read().decode("utf-8"))
                translations = []
                for item in payload[0]:
                    translations.append(item[0])
                return translations
        except Exception:
            time.sleep(0.5)
    return ["" for _ in masked_texts]

def load_cache(path):
    if os.path.exists(path):
        with open(path, "r", encoding="utf-8") as fh:
            return json.load(fh)
    return {}


def save_cache(path, data):
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)


if __name__ == "__main__":
    for locale, target_code in TARGETS.items():
        po_path = os.path.join(LANG_DIR, f"{locale}.po")
        if not os.path.exists(po_path):
            print(f"Skipping missing {po_path}")
            continue

        print(f"Processing {locale}")
        po = polib.pofile(po_path)
        cache_path = os.path.join(CACHE_DIR, f"{locale}.json")
        cache = load_cache(cache_path)
        updated = 0

        pending = []
        for entry in po:
            msgid = entry.msgid.strip()
            if not msgid or entry.msgstr.strip() or msgid in cache:
                if msgid in cache and not entry.msgstr.strip():
                    entry.msgstr = cache[msgid]
                continue

            masked, placeholders = mask_placeholders(msgid)
            pending.append((entry, msgid, masked, placeholders))

        for start in range(0, len(pending), BATCH_SIZE):
            batch = pending[start:start + BATCH_SIZE]
            masked_texts = [item[2] for item in batch]
            translations = translate_batch(masked_texts, target_code)
            for (entry, msgid, _, placeholders), translation in zip(batch, translations):
                if not translation:
                    continue

                final = restore_placeholders(translation, placeholders)
                cache[msgid] = final
                entry.msgstr = final
                updated += 1
            save_cache(cache_path, cache)
            time.sleep(0.2)
            print('.', end='', flush=True)

        if updated:
            po.save()
            print(f"  ✅ {updated} entries translated for {locale}")
        else:
            print(f"  nothing new translated for {locale}")
