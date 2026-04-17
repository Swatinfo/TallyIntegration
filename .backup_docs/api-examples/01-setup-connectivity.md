# 01 — Setup & Connectivity

Register Tally connections, manage multi-location setups, and verify health.

---

## Register Connections

```bash
# Mumbai Head Office
curl -X POST http://localhost:8000/api/tally/connections \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mumbai Head Office",
    "code": "MUM",
    "host": "192.168.1.10",
    "port": 9000,
    "company_name": "ABC Enterprises Pvt Ltd",
    "timeout": 30,
    "is_active": true
  }'

# Delhi Branch
curl -X POST http://localhost:8000/api/tally/connections \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Delhi Branch",
    "code": "DEL",
    "host": "192.168.2.10",
    "port": 9000,
    "company_name": "ABC Enterprises - Delhi"
  }'

# Same Tally instance, different company
curl -X POST http://localhost:8000/api/tally/connections \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sister Company",
    "code": "SIS",
    "host": "192.168.1.10",
    "port": 9000,
    "company_name": "XYZ Trading Co"
  }'
```

**Response** (201):
```json
{
  "success": true,
  "data": {
    "id": 1, "name": "Mumbai Head Office", "code": "MUM",
    "host": "192.168.1.10", "port": 9000,
    "company_name": "ABC Enterprises Pvt Ltd",
    "is_active": true
  },
  "message": "Connection created successfully"
}
```

## List / Get / Update / Delete Connections

```bash
curl http://localhost:8000/api/tally/connections              # List all
curl http://localhost:8000/api/tally/connections/1             # Get one

# Update host
curl -X PUT http://localhost:8000/api/tally/connections/2 \
  -H "Content-Type: application/json" \
  -d '{ "host": "192.168.2.20", "port": 9001 }'

# Deactivate
curl -X PUT http://localhost:8000/api/tally/connections/3 \
  -H "Content-Type: application/json" \
  -d '{ "is_active": false }'

# Delete
curl -X DELETE http://localhost:8000/api/tally/connections/3
```

## Health Checks

```bash
# Default (uses .env config)
curl http://localhost:8000/api/tally/health

# Per connection (by ID)
curl http://localhost:8000/api/tally/connections/1/health

# Per connection (by code, in route prefix)
curl http://localhost:8000/api/tally/MUM/health
curl http://localhost:8000/api/tally/DEL/health
```

**Response** (200):
```json
{
  "success": true,
  "data": {
    "connected": true,
    "url": "http://192.168.1.10:9000",
    "companies": ["ABC Enterprises Pvt Ltd", "XYZ Trading Co"]
  },
  "message": "TallyPrime is reachable"
}
```

**Response** (503 — not connected):
```json
{ "success": false, "data": { "connected": false, "url": "http://192.168.2.20:9001" }, "message": "Cannot connect to TallyPrime" }
```

## PHP

```php
use Modules\Tally\Services\TallyHttpClient;

$client = app(TallyHttpClient::class);
if ($client->isConnected()) {
    $companies = $client->getCompanies();
}
```
