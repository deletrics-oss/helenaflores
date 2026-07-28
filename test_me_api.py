import urllib.request
import json
import ssl

token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyNDQyNCIsImp0aSI6ImRjMTk1ZDIzYjQ1NTUzMDQ2YTA2MmEwZDU1OGYyNzNiZDBmZWZhYmQ0YmJjMWIzYzdmMDRhNzVlMzI4NTZlYmI3ZDc2ZTc0NGE5NGY1ZGFhIiwiaWF0IjoxNzgzMTA5Mzk4LjgyNzQzLCJuYmYiOjE3ODMxMDkzOTguODI3NDMyLCJleHAiOjE3ODU3MDEzOTguNzg5MTA3LCJzdWIiOiI5ZDE4YmJhNi0yMzJhLTQ3NjgtYTcyMi05YmRkZThmOTM0NzgiLCJzY29wZXMiOlsiY2FydC1yZWFkIiwiY2FydC13cml0ZSIsImNvbXBhbmllcy1yZWFkIiwiY29tcGFuaWVzLXdyaXRlIiwiY291cG9ucy1yZWFkIiwiY291cG9ucy13cml0ZSIsIm5vdGlmaWNhdGlvbnMtcmVhZCIsIm9yZGVycy1yZWFkIiwicHJvZHVjdHMtcmVhZCIsInByb2R1Y3RzLXdyaXRlIiwicHVyY2hhc2VzLXJlYWQiLCJzaGlwcGluZy1jYWxjdWxhdGUiLCJzaGlwcGluZy1jYW5jZWwiLCJzaGlwcGluZy1jaGVja291dCIsInNoaXBwaW5nLWNvbXBhbmllcyIsInNoaXBwaW5nLWdlbmVyYXRlIiwic2hpcHBpbmctcHJldmlldyIsInNoaXBwaW5nLXByaW50Iiwic2hpcHBpbmctc2hhcmUiLCJzaGlwcGluZy10cmFja2luZyIsImVjb21tZXJjZS1zaGlwcGluZyIsInRyYW5zYWN0aW9ucy1yZWFkIiwidXNlcnMtcmVhZCIsInVzZXJzLXdyaXRlIiwid2ViaG9va3MtcmVhZCIsIndlYmhvb2tzLXdyaXRlIl19.KOPjUAgs_ecjpzpQ70bsz0PvZkByxyAJM73-DHhk9yC2TreUrNOhzXI_Xum_nnv98TEVcxVS7RACS0To80VperNYiVjliKLRfYXXxfajcHoHzOU7zFV1OCqtAcgb9X4pOjW0KhAi_4R8RiIbvnnQAfIblH1RpS2fxLxsyQmctjhGip_Dviw2HFUokCCUzfw27ClFw4TW8t1ydxnKdX95c5xruHY5gWRYp3v-Cbg5_OK0M12Rwzk9XowSwOU2OCilLALFqeDpjokbtMz16FLeMa4fGTBl3HwdaIOJFrhmUWT1ZTtj0fGP_qqH_3bx7VETYfIgHvTyr_xntsDfrgrGfP3DVg6hScATmK2ByfSZeTLcY-XUXJH1wMKsQRmt7U2BhJ3jt3NbY7dOSSiAts-cYvnQDFPAtvWLcXsS5suLD6jskQ7nroA9lfcsMC2y37PQcb9JN2JU2kv9PDOUH_PlZKpfFDRMssOz3YEDufbZLvaOn9ipwhifo7pnpi-TkRIDJEhsNhHpjwL8xZGcNf-FVT5su3XDSulUaGI82NJLM9Hb3HtYb1Drbs9BT0NiGjM2AkZNf3r_oqTr3S9bg7PUutdsw_LBmKz-88UQwY6g9Ni0wSaRyoyqRjrFWPEUUZY67pthCgjf0jkdoqlC7WxFPVtpT5C4mWr7GtnHTEU6gwY"

oid = "a234095d-d232-40c8-b880-2cb625aceeb5" # Nalyton

url = f"https://www.melhorenvio.com.br/api/v2/me/orders/{oid}"
headers = {
    "Authorization": f"Bearer {token}",
    "Accept": "application/json",
    "User-Agent": "Fight Arcade Catalogo (deletrics@gmail.com)"
}
req = urllib.request.Request(url, headers=headers, method='GET')
context = ssl._create_unverified_context()

try:
    with urllib.request.urlopen(req, context=context) as response:
        data = json.loads(response.read().decode('utf-8'))
        
        # Flatten and print all string values to see if any tracking is there
        def print_strings(d, parent_key=''):
            if isinstance(d, dict):
                for k, v in d.items():
                    print_strings(v, f"{parent_key}.{k}" if parent_key else k)
            elif isinstance(d, list):
                for idx, item in enumerate(d):
                    print_strings(item, f"{parent_key}[{idx}]")
            elif isinstance(d, str):
                if len(d) > 5 and not d.startswith('http') and k != 'me_access_token':
                    print(f"{parent_key}: {d}")
            elif d is not None:
                print(f"{parent_key}: {d}")

        print_strings(data)
except Exception as e:
    print("ERROR:", e)
