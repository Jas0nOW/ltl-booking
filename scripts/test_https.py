import urllib.request

print(urllib.request.urlopen('https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=de&dt=t&q=test').read()[:80])
