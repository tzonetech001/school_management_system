# API - Mobile Integration Samples

Base URL (dev): `http://localhost/school_management_system-main/backend/api/index.php?uri=`

1) Login (get token + children)

curl (JSON POST):

```bash
curl -X POST "http://localhost/school_management_system-main/backend/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"phone":"0777123456"}'
```

Or via router:

```bash
curl -X POST "http://localhost/school_management_system-main/backend/api/index.php?uri=/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"0777123456"}'
```

Response includes `token` and `refresh_token`.

2) Use token for protected endpoints

Set header: `Authorization: Bearer <token>`

3) Get all children (optionally include results for exam + form)

Get children only:

```bash
curl "http://localhost/school_management_system-main/backend/api/index.php?uri=/share/children_results&phone=0777123456"
```

Get children with results (provide `exam_type_id` and `form`):

```bash
curl -X GET "http://localhost/school_management_system-main/backend/api/index.php?uri=/share/children_results&exam_type_id=1&form=Form%20five" \
  -H "Authorization: Bearer <token>"
```

4) Get single student result (result_report)

Using token:

```bash
curl -X POST "http://localhost/school_management_system-main/backend/api/index.php?uri=/share/result_report" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"student_id":123, "exam_type_id":1, "form":"Form five"}'
```

Or using phone (not recommended for production):

```bash
curl -X POST "http://localhost/school_management_system-main/backend/api/index.php?uri=/share/result_report" \
  -H "Content-Type: application/json" \
  -d '{"phone":"0777123456","student_id":123, "exam_type_id":1, "form":"Form five"}'
```

Notes
- After login the token is persisted server-side for 24 hours in table `api_tokens`. If the DB user cannot create tables, login will still succeed but token persistence may fail (error logged server-side).
- For production, switch to HTTPS and consider using JWTs or a token table with owner reference and refresh-token flow.
- You can call endpoints directly (e.g. `result_report.php`) or via `index.php?uri=/share/...` depending on your server configuration. The router supports the `/share/*` paths.
