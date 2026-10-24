#!/usr/bin/python3
"""Approved PR112 test-VPS upgrade only; no keys or privilege changes."""
import base64, datetime, fcntl, hashlib, importlib.util, json, os
from pathlib import Path
import socket, stat, subprocess, tempfile

BEFORE='9c80aac6c61594dbf34c247ca0c7fca3a1f1bdcf12dcf525501572385dfe544e'
AFTER='8e59a4527f348e1caaa0a99d92a94df54c05bdc8985e832cb4641858cbdfd146'
SOURCE='e3cda33b39b77abc7cf06ee4488ee5f7bb8ac4c0'
PAYLOAD='IyEvdXNyL2Jpbi9weXRob24zCiIiIlJvb3Qtb3duZWQsIGZvcmNlZC1jb21tYW5kIGdhdGV3YXkuIE5vIHNoZWxsIG9yIGFyYml0cmFyeSByZW1vdGUgY29tbWFuZHMuIiIiCmltcG9ydCBiYXNlNjQKaW1wb3J0IGZjbnRsCmltcG9ydCBoYXNobGliCmltcG9ydCBqc29uCmltcG9ydCBvcwpmcm9tIHBhdGhsaWIgaW1wb3J0IFBhdGgKaW1wb3J0IHJlCmltcG9ydCBzaHV0aWwKaW1wb3J0IHN1YnByb2Nlc3MKaW1wb3J0IHN5cwppbXBvcnQgdGVtcGZpbGUKCkFQUCA9IFBhdGgoJy9zcnYvcmFkaW9ydWJiZW4tdGVzdC9zdHVkaW8nKQpSRUxFQVNFUyA9IFBhdGgoJy9zcnYvcmFkaW9ydWJiZW4tdGVzdC9yZWxlYXNlcycpClNUQVRFID0gUGF0aCgnL3Zhci9saWIvcnItdnBzLWRlcGxveScpCkxJTUlUID0gMTIgKiAxMDI0ICogMTAyNAoKCiMgRml4ZWQsIHJlYWQtb25seSBvcGVyYXRpb25hbCBjaGVja3MuIE5ldmVyIGFjY2VwdCB1bml0IG5hbWVzIG9yIHNoZWxsIGZyb20gcmVxdWVzdHMuClNUQVRVU19VTklUUyA9ICgKICAgICduZ2lueC5zZXJ2aWNlJywgJ3BocDguMy1mcG0uc2VydmljZScsICdyci1lbnRyYS5zZXJ2aWNlJywKICAgICdyci1tdXNpYy1wbGF5b3V0LnNlcnZpY2UnLCAncnItcGxheW91dC5zZXJ2aWNlJywgJ3JyLXN0cmVhbS5zZXJ2aWNlJywKKQoKCmRlZiBzZXJ2aWNlX3N0YXR1cyhydW49Tm9uZSk6CiAgICAiIiJSZXR1cm4gYm91bmRlZCBzeXN0ZW1kIHN0YXRlIHdpdGhvdXQgbG9ncywgZW52aXJvbm1lbnQgb3IgY3JlZGVudGlhbHMuIiIiCiAgICBpZiBydW4gaXMgTm9uZToKICAgICAgICBydW4gPSBzdWJwcm9jZXNzLnJ1bgogICAgcmVzdWx0ID0ge30KICAgIGZvciB1bml0IGluIFNUQVRVU19VTklUUzoKICAgICAgICBwcm9jID0gcnVuKAogICAgICAgICAgICBbJy91c3IvYmluL3N5c3RlbWN0bCcsICdzaG93JywgdW5pdCwKICAgICAgICAgICAgICctLXByb3BlcnR5PUxvYWRTdGF0ZSxBY3RpdmVTdGF0ZSxTdWJTdGF0ZScsICctLW5vLXBhZ2VyJ10sCiAgICAgICAgICAgIGNhcHR1cmVfb3V0cHV0PVRydWUsIHRleHQ9VHJ1ZSwgdGltZW91dD01LCBjaGVjaz1GYWxzZSwKICAgICAgICApCiAgICAgICAgaWYgcHJvYy5yZXR1cm5jb2RlICE9IDA6CiAgICAgICAgICAgIHJlc3VsdFt1bml0XSA9IHsnbG9hZCc6ICd1bmtub3duJywgJ2FjdGl2ZSc6ICd1bmtub3duJywgJ3N1Yic6ICd1bmtub3duJ30KICAgICAgICAgICAgY29udGludWUKICAgICAgICBwcm9wcyA9IGRpY3QobGluZS5zcGxpdCgnPScsIDEpIGZvciBsaW5lIGluIHByb2Muc3Rkb3V0LnNwbGl0bGluZXMoKQogICAgICAgICAgICAgICAgICAgICBpZiAnPScgaW4gbGluZSkKICAgICAgICAjIE9ubHkgYm91bmRlZCwga25vd24gc3RhdGUgc3RyaW5ncyBhcmUgZXhwb3NlZCBpbiByZWNlaXB0cy4KICAgICAgICBkZWYgc3RhdGUoa2V5KToKICAgICAgICAgICAgdmFsdWUgPSBwcm9wcy5nZXQoa2V5LCAndW5rbm93bicpCiAgICAgICAgICAgIHJldHVybiB2YWx1ZSBpZiByZS5mdWxsbWF0Y2gocidbYS16LV17MSwzMn0nLCB2YWx1ZSkgZWxzZSAndW5rbm93bicKICAgICAgICByZXN1bHRbdW5pdF0gPSB7CiAgICAgICAgICAgICdsb2FkJzogc3RhdGUoJ0xvYWRTdGF0ZScpLAogICAgICAgICAgICAnYWN0aXZlJzogc3RhdGUoJ0FjdGl2ZVN0YXRlJyksCiAgICAgICAgICAgICdzdWInOiBzdGF0ZSgnU3ViU3RhdGUnKSwKICAgICAgICB9CiAgICByZXR1cm4geydzdGF0dXMnOiAnc2VydmljZS1zdGF0dXMnLCAnc2VydmljZXMnOiByZXN1bHR9CgoKZGVmIGRpZ2VzdChkYXRhKToKICAgIHJldHVybiBoYXNobGliLnNoYTI1NihkYXRhKS5oZXhkaWdlc3QoKQoKCmRlZiBhbGxvd2VkKG5hbWUpOgogICAgcmV0dXJuIChpc2luc3RhbmNlKG5hbWUsIHN0cikgYW5kCiAgICAgICAgICAgIHJlLmZ1bGxtYXRjaChyJyhhcHB8cHVibGljKS9bQS1aYS16MC05Xy8tXStcLihwaHB8bWpzfGpzfGNzcyknLCBuYW1lKSBhbmQKICAgICAgICAgICAgYWxsKHAgbm90IGluICgnJywgJy4nLCAnLi4nKSBmb3IgcCBpbiBuYW1lLnNwbGl0KCcvJykpKQoKCmRlZiByb290ZWQoYXBwLCBuYW1lKToKICAgIGlmIG5vdCBhbGxvd2VkKG5hbWUpOgogICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ1BhdGggaXMgb3V0c2lkZSB0aGUgY29kZSBhbGxvd2xpc3QnKQogICAgcGF0aCA9IGFwcCAvIG5hbWUKICAgIGZvciBwYXJ0IGluIFtwYXRoLCAqcGF0aC5wYXJlbnRzXToKICAgICAgICBpZiBwYXJ0ID09IGFwcDoKICAgICAgICAgICAgYnJlYWsKICAgICAgICBpZiBwYXJ0LmlzX3N5bWxpbmsoKToKICAgICAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignU3ltbGluayBpbiBjb2RlIHBhdGgnKQogICAgaWYgcGF0aC5leGlzdHMoKSBhbmQgKG5vdCBwYXRoLmlzX2ZpbGUoKSBvciBwYXRoLnN0YXQoKS5zdF9ubGluayAhPSAxKToKICAgICAgICByYWlzZSBWYWx1ZUVycm9yKCdDb2RlIHRhcmdldCBpcyBub3QgYSByZWd1bGFyIHNpbmdsZS1saW5rIGZpbGUnKQogICAgcmV0dXJuIHBhdGgKCgpkZWYgc25hcHNob3QoYXBwKToKICAgIHJlc3VsdCA9IHt9CiAgICBmb3IgZm9sZGVyIGluICgnYXBwJywgJ3B1YmxpYycpOgogICAgICAgIGZvciBwYXRoIGluIHNvcnRlZCgoYXBwIC8gZm9sZGVyKS5yZ2xvYignKicpKToKICAgICAgICAgICAgbmFtZSA9IHBhdGgucmVsYXRpdmVfdG8oYXBwKS5hc19wb3NpeCgpCiAgICAgICAgICAgIGlmIGFsbG93ZWQobmFtZSk6CiAgICAgICAgICAgICAgICBwYXRoID0gcm9vdGVkKGFwcCwgbmFtZSkKICAgICAgICAgICAgICAgIGlmIHBhdGguaXNfZmlsZSgpOgogICAgICAgICAgICAgICAgICAgIHJlc3VsdFtuYW1lXSA9IGRpZ2VzdChwYXRoLnJlYWRfYnl0ZXMoKSkKICAgIHJldHVybiByZXN1bHQKCgpkZWYgdmFsaWRhdGUoYXBwLCByZXF1ZXN0KToKICAgIGlmIG5vdCByZS5mdWxsbWF0Y2goJ1swLTlhLWZdezQwfScsIHJlcXVlc3QuZ2V0KCdjb21taXQnLCAnJykpOgogICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ0V4YWN0IHNvdXJjZSBjb21taXQgcmVxdWlyZWQnKQogICAgZmlsZXMgPSByZXF1ZXN0LmdldCgnZmlsZXMnKQogICAgaWYgbm90IGlzaW5zdGFuY2UoZmlsZXMsIGxpc3QpIG9yIG5vdCAxIDw9IGxlbihmaWxlcykgPD0gNjQ6CiAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignRXhwZWN0ZWQgMSB0byA2NCBjb2RlIGZpbGVzJykKICAgIGNoYW5nZXMgPSBbXQogICAgc2VlbiA9IHNldCgpCiAgICBmb3IgZW50cnkgaW4gZmlsZXM6CiAgICAgICAgbmFtZSA9IGVudHJ5WydwYXRoJ10KICAgICAgICBwYXRoID0gcm9vdGVkKGFwcCwgbmFtZSkKICAgICAgICBpZiBuYW1lIGluIHNlZW46CiAgICAgICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ0R1cGxpY2F0ZSBwYXRoJykKICAgICAgICBzZWVuLmFkZChuYW1lKQogICAgICAgIGRhdGEgPSBiYXNlNjQuYjY0ZGVjb2RlKGVudHJ5Wydjb250ZW50J10sIHZhbGlkYXRlPVRydWUpCiAgICAgICAgaWYgbGVuKGRhdGEpID4gMiAqIDEwMjQgKiAxMDI0IG9yIGRpZ2VzdChkYXRhKSAhPSBlbnRyeVsnYWZ0ZXInXToKICAgICAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignQ2FuZGlkYXRlIGNoZWNrc3VtIG9yIHNpemUgbWlzbWF0Y2gnKQogICAgICAgIG9sZCA9IHBhdGgucmVhZF9ieXRlcygpIGlmIHBhdGguZXhpc3RzKCkgZWxzZSBOb25lCiAgICAgICAgYmVmb3JlID0gZGlnZXN0KG9sZCkgaWYgb2xkIGlzIG5vdCBOb25lIGVsc2UgTm9uZQogICAgICAgIGlmIGJlZm9yZSAhPSBlbnRyeVsnYmVmb3JlJ106CiAgICAgICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ0FjdGl2ZSBjb2RlIGRpZmZlcnMgZnJvbSByZXZpZXdlZCBiYXNlbGluZTogJyArIG5hbWUpCiAgICAgICAgaWYgbm90IHBhdGgucGFyZW50LmlzX2RpcigpOgogICAgICAgICAgICByYWlzZSBWYWx1ZUVycm9yKCdQYXJlbnQgZGlyZWN0b3J5IG11c3QgYWxyZWFkeSBleGlzdCcpCiAgICAgICAgY2hhbmdlcy5hcHBlbmQoKHBhdGgsIGRhdGEsIG9sZCwgcGF0aC5zdGF0KCkgaWYgb2xkIGlzIG5vdCBOb25lIGVsc2UgTm9uZSkpCiAgICByZXR1cm4gY2hhbmdlcwoKCmRlZiBhdG9taWMocGF0aCwgZGF0YSwgbWV0YWRhdGE9Tm9uZSk6CiAgICBmZCwgdG1wID0gdGVtcGZpbGUubWtzdGVtcChwcmVmaXg9Jy5yci1kZXBsb3ktJywgZGlyPXBhdGgucGFyZW50KQogICAgdHJ5OgogICAgICAgIHdpdGggb3MuZmRvcGVuKGZkLCAnd2InKSBhcyBzdHJlYW06CiAgICAgICAgICAgIHN0cmVhbS53cml0ZShkYXRhKQogICAgICAgICAgICBzdHJlYW0uZmx1c2goKQogICAgICAgICAgICBvcy5mc3luYyhzdHJlYW0uZmlsZW5vKCkpCiAgICAgICAgICAgIG9zLmZjaG1vZChzdHJlYW0uZmlsZW5vKCksIChtZXRhZGF0YS5zdF9tb2RlICYgMG83NzcpIGlmIG1ldGFkYXRhIGVsc2UgMG82NDQpCiAgICAgICAgICAgIGlmIG1ldGFkYXRhOgogICAgICAgICAgICAgICAgb3MuZmNob3duKHN0cmVhbS5maWxlbm8oKSwgbWV0YWRhdGEuc3RfdWlkLCBtZXRhZGF0YS5zdF9naWQpCiAgICAgICAgb3MucmVwbGFjZSh0bXAsIHBhdGgpCiAgICAgICAgZGlyZWN0b3J5ID0gb3Mub3BlbihwYXRoLnBhcmVudCwgb3MuT19ESVJFQ1RPUlkpCiAgICAgICAgdHJ5OgogICAgICAgICAgICBvcy5mc3luYyhkaXJlY3RvcnkpCiAgICAgICAgZmluYWxseToKICAgICAgICAgICAgb3MuY2xvc2UoZGlyZWN0b3J5KQogICAgZmluYWxseToKICAgICAgICBpZiBvcy5wYXRoLmV4aXN0cyh0bXApOgogICAgICAgICAgICBvcy51bmxpbmsodG1wKQoKCmRlZiBoZWFsdGgoKToKICAgIGZvciBzZXJ2aWNlIGluICgncGhwOC4zLWZwbScsICdyci1lbnRyYScpOgogICAgICAgIHN1YnByb2Nlc3MucnVuKFsnL3Vzci9iaW4vc3lzdGVtY3RsJywgJ2lzLWFjdGl2ZScsICctLXF1aWV0Jywgc2VydmljZV0sIGNoZWNrPVRydWUpCiAgICBzdWJwcm9jZXNzLnJ1bihbJy91c3IvYmluL2N1cmwnLCAnLS1mYWlsJywgJy0tc2lsZW50JywgJy0tc2hvdy1lcnJvcicsCiAgICAgICAgICAgICAgICAgICAgJy0tbWF4LXRpbWUnLCAnMjAnLCAnLS1vdXRwdXQnLCAnL2Rldi9udWxsJywKICAgICAgICAgICAgICAgICAgICAnaHR0cHM6Ly81LjI0OS4yNTIuNzgvbG9naW4ucGhwJ10sIGNoZWNrPVRydWUpCgoKZGVmIHJlbG9hZF9waHAoKToKICAgIHN1YnByb2Nlc3MucnVuKFsnL3Vzci9iaW4vc3lzdGVtY3RsJywgJ3JlbG9hZCcsICdwaHA4LjMtZnBtJ10sIGNoZWNrPVRydWUpCiAgICAjIFNlcGFyYXRlIEVudHJhIEZQTSBoYXMgbm8gRXhlY1JlbG9hZDsgcmVzdGFydGluZyBjbGVhcnMgaXRzIGNvZGUgY2FjaGUuCiAgICBzdWJwcm9jZXNzLnJ1bihbJy91c3IvYmluL3N5c3RlbWN0bCcsICdyZXN0YXJ0JywgJ3JyLWVudHJhJ10sIGNoZWNrPVRydWUpCgoKZGVmIGRlcGxveShhcHAsIHN0YXRlLCByZXF1ZXN0LCByZWxvYWRfaG9vaz1yZWxvYWRfcGhwLCBoZWFsdGhfaG9vaz1oZWFsdGgpOgogICAgY2hhbmdlcyA9IHZhbGlkYXRlKGFwcCwgcmVxdWVzdCkKICAgIGlmIChzdGF0ZSAvICdwZW5kaW5nLmpzb24nKS5leGlzdHMoKToKICAgICAgICByYWlzZSBWYWx1ZUVycm9yKCdQcmV2aW91cyBkZXBsb3ltZW50IG5lZWRzIHJlY292ZXJ5OyBubyBuZXcgd3JpdGVzJykKICAgIGhlYWx0aF9ob29rKCkKICAgIGJhY2t1cCA9IFBhdGgodGVtcGZpbGUubWtkdGVtcChwcmVmaXg9J2JhY2t1cC0nLCBkaXI9c3RhdGUpKQogICAgcmVjZWlwdCA9IHsnY29tbWl0JzogcmVxdWVzdFsnY29tbWl0J10sICdmaWxlcyc6IHJlcXVlc3RbJ2ZpbGVzJ10sICdiYWNrdXAnOiBzdHIoYmFja3VwKX0KICAgICMgUmVjZWlwdHMgY29udGFpbiBjaGVja3N1bXMgb25seSwgbm90IHNvdXJjZSBjb2RlIG9yIHByaXZhdGUgYXBwIGRhdGEuCiAgICByZWNlaXB0WydmaWxlcyddID0gW3trOiB2IGZvciBrLCB2IGluIGYuaXRlbXMoKSBpZiBrICE9ICdjb250ZW50J30gZm9yIGYgaW4gcmVxdWVzdFsnZmlsZXMnXV0KICAgIGZvciBwYXRoLCBkYXRhLCBvbGQsIG1ldGEgaW4gY2hhbmdlczoKICAgICAgICBpZiBvbGQgaXMgbm90IE5vbmU6CiAgICAgICAgICAgIGRlc3QgPSBiYWNrdXAgLyBwYXRoLnJlbGF0aXZlX3RvKGFwcCkKICAgICAgICAgICAgZGVzdC5wYXJlbnQubWtkaXIocGFyZW50cz1UcnVlLCBleGlzdF9vaz1UcnVlKQogICAgICAgICAgICBzaHV0aWwuY29weTIocGF0aCwgZGVzdCkKICAgICAgICAgICAgb3MuY2hvd24oZGVzdCwgbWV0YS5zdF91aWQsIG1ldGEuc3RfZ2lkKQogICAgYXRvbWljKGJhY2t1cCAvICdtYW5pZmVzdC5qc29uJywganNvbi5kdW1wcyhyZWNlaXB0LCBpbmRlbnQ9MikuZW5jb2RlKCkpCiAgICBhdG9taWMoc3RhdGUgLyAncGVuZGluZy5qc29uJywganNvbi5kdW1wcyhyZWNlaXB0KS5lbmNvZGUoKSkKICAgIHdyaXR0ZW4gPSBbXQogICAgdHJ5OgogICAgICAgIGZvciBwYXRoLCBkYXRhLCBvbGQsIG1ldGEgaW4gY2hhbmdlczoKICAgICAgICAgICAgY3VycmVudCA9IHBhdGgucmVhZF9ieXRlcygpIGlmIHBhdGguZXhpc3RzKCkgZWxzZSBOb25lCiAgICAgICAgICAgIGlmIGN1cnJlbnQgIT0gb2xkOgogICAgICAgICAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignQ29uY3VycmVudCBjb2RlIGNoYW5nZSBkZXRlY3RlZCcpCiAgICAgICAgICAgIGF0b21pYyhwYXRoLCBkYXRhLCBtZXRhKQogICAgICAgICAgICB3cml0dGVuLmFwcGVuZCgocGF0aCwgZGF0YSwgb2xkLCBtZXRhKSkKICAgICAgICByZWxvYWRfaG9vaygpCiAgICAgICAgaGVhbHRoX2hvb2soKQogICAgICAgIGZvciBwYXRoLCBkYXRhLCBvbGQsIG1ldGEgaW4gd3JpdHRlbjoKICAgICAgICAgICAgaWYgcGF0aC5yZWFkX2J5dGVzKCkgIT0gZGF0YToKICAgICAgICAgICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ1Bvc3QtZGVwbG95IGNoZWNrc3VtIG1pc21hdGNoJykKICAgICAgICByZWNlaXB0WydzdGF0dXMnXSA9ICdkZXBsb3llZCcKICAgICAgICBhdG9taWMoc3RhdGUgLyAnbGFzdC1zdWNjZXNzLmpzb24nLCBqc29uLmR1bXBzKHJlY2VpcHQsIGluZGVudD0yKS5lbmNvZGUoKSkKICAgICAgICAoc3RhdGUgLyAncGVuZGluZy5qc29uJykudW5saW5rKCkKICAgICAgICByZXR1cm4gcmVjZWlwdAogICAgZXhjZXB0IEJhc2VFeGNlcHRpb246CiAgICAgICAgZm9yIHBhdGgsIGRhdGEsIG9sZCwgbWV0YSBpbiByZXZlcnNlZCh3cml0dGVuKToKICAgICAgICAgICAgaWYgbm90IHBhdGguaXNfZmlsZSgpIG9yIHBhdGguaXNfc3ltbGluaygpIG9yIHBhdGgucmVhZF9ieXRlcygpICE9IGRhdGE6CiAgICAgICAgICAgICAgICByYWlzZSBSdW50aW1lRXJyb3IoJ1JvbGxiYWNrIGJsb2NrZWQgYnkgY29uY3VycmVudCBjaGFuZ2U7IHBlbmRpbmcgam91cm5hbCByZXRhaW5lZCcpCiAgICAgICAgICAgIGlmIG9sZCBpcyBOb25lOgogICAgICAgICAgICAgICAgcGF0aC51bmxpbmsoKQogICAgICAgICAgICBlbHNlOgogICAgICAgICAgICAgICAgYXRvbWljKHBhdGgsIG9sZCwgbWV0YSkKICAgICAgICByZWxvYWRfaG9vaygpCiAgICAgICAgaGVhbHRoX2hvb2soKQogICAgICAgIChzdGF0ZSAvICdwZW5kaW5nLmpzb24nKS51bmxpbmsoKQogICAgICAgIHJhaXNlCgoKZGVmIG1haW4oKToKICAgIG9zLnVtYXNrKDBvMDc3KQogICAgaWYgb3MuZ2V0ZXVpZCgpICE9IDA6CiAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignR2F0ZXdheSBtdXN0IHJ1biB0aHJvdWdoIHRoZSBmaXhlZCBzdWRvIHJ1bGUnKQogICAgYXBwID0gQVBQLnJlc29sdmUoc3RyaWN0PVRydWUpCiAgICBpZiBhcHAucGFyZW50ICE9IFJFTEVBU0VTIG9yIG5vdCBhcHAubmFtZS5zdGFydHN3aXRoKCdzdHVkaW8tJyk6CiAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignVW5leHBlY3RlZCBhY3RpdmUgU3R1ZGlvIHJlbGVhc2UnKQogICAgIyBObyB3cml0YWJsZSBwYXJlbnQgbWF5IHJlZGlyZWN0IHJvb3Qgd3JpdGVzIHRocm91Z2ggYSBzdWJzdGl0dXRlZCBwYXRoLgogICAgZm9yIGZvbGRlciBpbiBbYXBwLCAqYXBwLnBhcmVudHMsIEFQUC5wYXJlbnRdOgogICAgICAgIHN0ID0gZm9sZGVyLnN0YXQoKQogICAgICAgIGlmIHN0LnN0X3VpZCAhPSAwIG9yIHN0LnN0X21vZGUgJiAwbzAyMjoKICAgICAgICAgICAgcmFpc2UgVmFsdWVFcnJvcignUmVsZWFzZSBhbmNlc3RyeSBtdXN0IGJlIHJvb3Qtb3duZWQgYW5kIG5vbi13cml0YWJsZScpCiAgICBTVEFURS5ta2Rpcihtb2RlPTBvNzAwLCBleGlzdF9vaz1UcnVlKQogICAgaWYgU1RBVEUuaXNfc3ltbGluaygpIG9yIFNUQVRFLnN0YXQoKS5zdF91aWQgIT0gMCBvciBTVEFURS5zdGF0KCkuc3RfbW9kZSAmIDBvMDc3OgogICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ1Vuc2FmZSBnYXRld2F5IHN0YXRlIGRpcmVjdG9yeScpCiAgICB3aXRoIChTVEFURSAvICdsb2NrJykub3BlbignYScpIGFzIGxvY2s6CiAgICAgICAgZmNudGwuZmxvY2sobG9jaywgZmNudGwuTE9DS19FWCB8IGZjbnRsLkxPQ0tfTkIpCiAgICAgICAgcmF3ID0gc3lzLnN0ZGluLmJ1ZmZlci5yZWFkKExJTUlUICsgMSkKICAgICAgICBpZiBsZW4ocmF3KSA+IExJTUlUOgogICAgICAgICAgICByYWlzZSBWYWx1ZUVycm9yKCdSZXF1ZXN0IHRvbyBsYXJnZScpCiAgICAgICAgcmVxdWVzdCA9IGpzb24ubG9hZHMocmF3KQogICAgICAgIG1vZGUgPSByZXF1ZXN0LmdldCgnbW9kZScpCiAgICAgICAgaWYgbW9kZSA9PSAnc3RhdHVzJzoKICAgICAgICAgICAgcmVzdWx0ID0gc2VydmljZV9zdGF0dXMoKQogICAgICAgIGVsaWYgbW9kZSA9PSAnc25hcHNob3QnOgogICAgICAgICAgICByZXN1bHQgPSB7J3N0YXR1cyc6ICdzbmFwc2hvdCcsICdhcHAnOiBzdHIoYXBwKSwgJ2ZpbGVzJzogc25hcHNob3QoYXBwKSwKICAgICAgICAgICAgICAgICAgICAgICdyZWNvdmVyeVJlcXVpcmVkJzogKFNUQVRFIC8gJ3BlbmRpbmcuanNvbicpLmV4aXN0cygpfQogICAgICAgIGVsaWYgbW9kZSBpbiAoJ2NoZWNrJywgJ2FwcGx5Jyk6CiAgICAgICAgICAgICMgQ29kZSBzdWJkaXJlY3RvcmllcyBtdXN0IGFsc28gYmUgaW1tdXRhYmxlIHRvIHJ1bnRpbWUgdXNlcnMuCiAgICAgICAgICAgIGZvciBpdGVtIGluIHJlcXVlc3QuZ2V0KCdmaWxlcycsIFtdKToKICAgICAgICAgICAgICAgIHBhdGggPSByb290ZWQoYXBwLCBpdGVtWydwYXRoJ10pCiAgICAgICAgICAgICAgICBpZiBwYXRoLmV4aXN0cygpOgogICAgICAgICAgICAgICAgICAgIHN0ID0gcGF0aC5zdGF0KCkKICAgICAgICAgICAgICAgICAgICBpZiBzdC5zdF91aWQgIT0gMCBvciBzdC5zdF9tb2RlICYgMG8wMjI6CiAgICAgICAgICAgICAgICAgICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ0NvZGUgZmlsZSBtdXN0IGJlIHJvb3Qtb3duZWQgYW5kIG5vbi13cml0YWJsZScpCiAgICAgICAgICAgICAgICBmb3IgZm9sZGVyIGluIHBhdGgucGFyZW50czoKICAgICAgICAgICAgICAgICAgICBzdCA9IGZvbGRlci5zdGF0KCkKICAgICAgICAgICAgICAgICAgICBpZiBzdC5zdF91aWQgIT0gMCBvciBzdC5zdF9tb2RlICYgMG8wMjI6CiAgICAgICAgICAgICAgICAgICAgICAgIHJhaXNlIFZhbHVlRXJyb3IoJ0NvZGUgcGFyZW50IG11c3QgYmUgcm9vdC1vd25lZCBhbmQgbm9uLXdyaXRhYmxlJykKICAgICAgICAgICAgICAgICAgICBpZiBmb2xkZXIgPT0gYXBwOgogICAgICAgICAgICAgICAgICAgICAgICBicmVhawogICAgICAgICAgICBpZiBtb2RlID09ICdjaGVjayc6CiAgICAgICAgICAgICAgICB2YWxpZGF0ZShhcHAsIHJlcXVlc3QpCiAgICAgICAgICAgICAgICByZXN1bHQgPSB7J3N0YXR1cyc6ICdjaGVja2VkJywgJ2NvbW1pdCc6IHJlcXVlc3RbJ2NvbW1pdCddLCAnY291bnQnOiBsZW4ocmVxdWVzdFsnZmlsZXMnXSl9CiAgICAgICAgICAgIGVsc2U6CiAgICAgICAgICAgICAgICByZXN1bHQgPSBkZXBsb3koYXBwLCBTVEFURSwgcmVxdWVzdCkKICAgICAgICBlbHNlOgogICAgICAgICAgICByYWlzZSBWYWx1ZUVycm9yKCdVbnN1cHBvcnRlZCBtb2RlJykKICAgICAgICBwcmludChqc29uLmR1bXBzKHJlc3VsdCkpCgoKaWYgX19uYW1lX18gPT0gJ19fbWFpbl9fJzoKICAgIHRyeToKICAgICAgICBtYWluKCkKICAgIGV4Y2VwdCBFeGNlcHRpb24gYXMgZXJyb3I6CiAgICAgICAgcHJpbnQoJ1NUT1BQOiAnICsgc3RyKGVycm9yKSwgZmlsZT1zeXMuc3RkZXJyKQogICAgICAgIHN5cy5leGl0KDEpCg=='
TARGET=Path('/usr/local/libexec/rr-vps-deploy.py')
STATE=Path('/var/lib/rr-vps-deploy')
GUARDS=[('/etc/sudoers.d/rr-vps-deploy',0o440),('/home/rr-deploy/.ssh/authorized_keys',0o644)]

def require(ok, message):
    if not ok: raise RuntimeError(message)

def digest(data): return hashlib.sha256(data).hexdigest()

def ancestry(path):
    for p in [path, *path.parents]:
        s=p.lstat()
        require(not stat.S_ISLNK(s.st_mode) and s.st_uid==0 and not s.st_mode & 0o022,
                'Unsafe ownership/path: '+str(p))

def checked_file(path, mode):
    ancestry(path)
    s=path.stat()
    require(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_gid==0
            and stat.S_IMODE(s.st_mode)==mode, 'Unexpected metadata: '+str(path))
    return digest(path.read_bytes())

def fixed_probe(mode):
    p=subprocess.run(['/usr/bin/sudo','-u','rr-deploy','/usr/bin/sudo','-n',
        '/usr/bin/python3','-I',str(TARGET)], input=json.dumps({'mode':mode}),
        text=True,capture_output=True,timeout=60)
    require(p.returncode==0, 'Fixed sudo probe failed: '+mode)
    return json.loads(p.stdout)

def main():
    require(os.geteuid()==0, 'Run with existing sudo on test-VPS')
    require(socket.gethostname()=='vps-5363103', 'Wrong hostname')
    os.umask(0o077)
    candidate=base64.b64decode(PAYLOAD,validate=True)
    require(digest(candidate)==AFTER,'Candidate checksum mismatch')
    compile(candidate,str(TARGET),'exec')
    ancestry(STATE)
    require(stat.S_IMODE(STATE.stat().st_mode)==0o700,'Unsafe state permissions')
    guards={p:checked_file(Path(p),m) for p,m in GUARDS}
    require(checked_file(TARGET,0o644)==BEFORE,'Active gateway differs from reviewed baseline')
    before=fixed_probe('snapshot')
    require(before.get('status')=='snapshot' and before.get('recoveryRequired') is False,
            'Invalid snapshot or pending recovery')
    lockpath=STATE/'lock'
    checked_file(lockpath,0o600)
    with lockpath.open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        require(not (STATE/'pending.json').exists(),'Pending deployment')
        require(checked_file(TARGET,0o644)==BEFORE,'Concurrent gateway change')
        parent=STATE/'gateway-upgrades'
        if not parent.exists(): parent.mkdir(mode=0o700)
        ancestry(parent)
        require(stat.S_IMODE(parent.stat().st_mode)==0o700,'Unsafe backup parent')
        backup=Path(tempfile.mkdtemp(prefix='112-',dir=parent))
        old=TARGET.read_bytes()
        with (backup/'gateway.before.py').open('xb') as f:
            f.write(old); f.flush(); os.fsync(f.fileno())
        require(digest((backup/'gateway.before.py').read_bytes())==BEFORE,'Backup verification failed')
        (backup/'manifest.json').write_text(json.dumps({'source':SOURCE,'before':BEFORE,
            'after':AFTER,'uid':0,'gid':0,'mode':'0644',
            'timeUTC':datetime.datetime.now(datetime.timezone.utc).isoformat()},indent=2))
        (backup/'snapshot.before.json').write_text(json.dumps(before))
        spec=importlib.util.spec_from_file_location('reviewed_gateway',TARGET)
        g=importlib.util.module_from_spec(spec); spec.loader.exec_module(g)
        app=g.APP.resolve(strict=True)
        require(app.parent==g.RELEASES and app.name.startswith('studio-'),'Unexpected release')
        ancestry(app)
        require(str(app)==before['app'] and g.snapshot(app)==before['files'],'Concurrent Studio change')
        try:
            g.atomic(TARGET,candidate,TARGET.stat())
        except BaseException:
            if TARGET.is_file() and not TARGET.is_symlink() and digest(TARGET.read_bytes())==AFTER:
                g.atomic(TARGET,old,TARGET.stat())
            raise
    try:
        require(checked_file(TARGET,0o644)==AFTER,'Installed checksum mismatch')
        status=fixed_probe('status')
        expected={'nginx.service','php8.3-fpm.service','rr-entra.service',
            'rr-music-playout.service','rr-playout.service','rr-stream.service'}
        require(status.get('status')=='service-status' and set(status['services'])==expected,
                'Invalid status response')
        require(all(set(v)=={'load','active','sub'} for v in status['services'].values()),
                'Unexpected status fields')
        after=fixed_probe('snapshot')
        require(after==before,'Studio snapshot changed during installation')
        for p,h in guards.items():
            require(digest(Path(p).read_bytes())==h,'SSH/sudoers changed')
        receipt={'status':'installed-local-fixed-sudo-verified','source':SOURCE,
            'sha256':AFTER,'backup':str(backup),'app':after['app'],
            'codeFiles':len(after['files']),'services':status['services'],
            'sshTransportVerified':False,
            'timeUTC':datetime.datetime.now(datetime.timezone.utc).isoformat()}
        (backup/'receipt.json').write_text(json.dumps(receipt,indent=2))
        print(json.dumps(receipt,indent=2))
    except BaseException:
        with lockpath.open('a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            require(checked_file(TARGET,0o644)==AFTER,'Rollback stopped: concurrent change')
            require(digest((backup/'gateway.before.py').read_bytes())==BEFORE,'Invalid backup')
            g.atomic(TARGET,old,TARGET.stat())
        require(checked_file(TARGET,0o644)==BEFORE,'Rollback checksum failed')
        restored=fixed_probe('snapshot')
        require(restored==before,'Rollback snapshot differs; inspect privately')
        print('ROLLED_BACK: original gateway restored; backup='+str(backup))
        raise

if __name__=='__main__':
    try: main()
    except Exception as exc:
        print('STOPP: '+str(exc))
        raise SystemExit(1)
