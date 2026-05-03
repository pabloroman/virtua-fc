{{/*
Expand the name of the chart.
*/}}
{{- define "virtua-fc.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Fully qualified app name.
*/}}
{{- define "virtua-fc.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- $name := default .Chart.Name .Values.nameOverride -}}
{{- if contains $name .Release.Name -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}
{{- end -}}

{{/*
Chart label
*/}}
{{- define "virtua-fc.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Common labels
*/}}
{{- define "virtua-fc.labels" -}}
helm.sh/chart: {{ include "virtua-fc.chart" . }}
{{ include "virtua-fc.selectorLabels" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{/*
Selector labels
*/}}
{{- define "virtua-fc.selectorLabels" -}}
app.kubernetes.io/name: {{ include "virtua-fc.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/*
ServiceAccount name
*/}}
{{- define "virtua-fc.serviceAccountName" -}}
{{- if .Values.serviceAccount.create -}}
{{- default (include "virtua-fc.fullname" .) .Values.serviceAccount.name -}}
{{- else -}}
{{- default "default" .Values.serviceAccount.name -}}
{{- end -}}
{{- end -}}

{{/*
Image reference
*/}}
{{- define "virtua-fc.image" -}}
{{- $tag := default .Chart.AppVersion .Values.image.tag -}}
{{- printf "%s:%s" .Values.image.repository $tag -}}
{{- end -}}

{{/*
Redis host (in-cluster Service or external)
*/}}
{{- define "virtua-fc.redisHost" -}}
{{- if .Values.redis.internal.enabled -}}
{{- printf "%s-redis" (include "virtua-fc.fullname" .) -}}
{{- else -}}
{{- .Values.redis.external.host -}}
{{- end -}}
{{- end -}}

{{/*
Redis port
*/}}
{{- define "virtua-fc.redisPort" -}}
{{- if .Values.redis.internal.enabled -}}
6379
{{- else -}}
{{- .Values.redis.external.port -}}
{{- end -}}
{{- end -}}

{{/*
Pulse DB host (falls back to main DB host if unset)
*/}}
{{- define "virtua-fc.pulseDbHost" -}}
{{- if .Values.pulseDatabase.host -}}
{{- .Values.pulseDatabase.host -}}
{{- else -}}
{{- .Values.database.host -}}
{{- end -}}
{{- end -}}

{{/*
Shared env block — referenced from each workload via envFrom + env.
Keep this aligned with .env.example.
*/}}
{{- define "virtua-fc.appEnv" -}}
- name: APP_ENV
  value: {{ .Values.app.env | quote }}
- name: APP_DEBUG
  value: {{ .Values.app.debug | quote }}
- name: APP_URL
  value: {{ .Values.app.url | quote }}
- name: APP_LOCALE
  value: {{ .Values.app.locale | quote }}
- name: APP_FALLBACK_LOCALE
  value: {{ .Values.app.fallbackLocale | quote }}
- name: APP_FAKER_LOCALE
  value: {{ .Values.app.fakerLocale | quote }}
- name: APP_TIMEZONE
  value: {{ .Values.app.timezone | quote }}
{{- if .Values.app.cdnUrl }}
- name: CDN_URL
  value: {{ .Values.app.cdnUrl | quote }}
{{- end }}
- name: LOG_CHANNEL
  value: {{ .Values.app.logChannel | quote }}
- name: LOG_STACK
  value: {{ .Values.app.logStack | quote }}
- name: LOG_LEVEL
  value: {{ .Values.app.logLevel | quote }}
- name: APP_KEY
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.name }}
      key: APP_KEY

# Database
- name: DB_CONNECTION
  value: pgsql
- name: DB_HOST
  value: {{ .Values.database.host | quote }}
- name: DB_PORT
  value: {{ .Values.database.port | quote }}
- name: DB_DATABASE
  value: {{ .Values.database.name | quote }}
- name: DB_USERNAME
  value: {{ .Values.database.username | quote }}
- name: DB_SSLMODE
  value: {{ .Values.database.sslmode | quote }}
- name: DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.name }}
      key: DB_PASSWORD

{{- if .Values.pulseDatabase.enabled }}
# Pulse database
- name: PULSE_DB_CONNECTION
  value: pulse_pgsql
- name: PULSE_DB_HOST
  value: {{ include "virtua-fc.pulseDbHost" . | quote }}
- name: PULSE_DB_PORT
  value: {{ .Values.pulseDatabase.port | quote }}
- name: PULSE_DB_DATABASE
  value: {{ .Values.pulseDatabase.name | quote }}
- name: PULSE_DB_USERNAME
  value: {{ .Values.pulseDatabase.username | quote }}
- name: PULSE_DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.name }}
      key: PULSE_DB_PASSWORD
      optional: true
{{- end }}

# Redis
- name: REDIS_CLIENT
  value: phpredis
- name: REDIS_HOST
  value: {{ include "virtua-fc.redisHost" . | quote }}
- name: REDIS_PORT
  value: {{ include "virtua-fc.redisPort" . | quote }}
- name: REDIS_DB
  value: "0"
- name: REDIS_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.name }}
      key: REDIS_PASSWORD
      optional: true

# Cache / queue / session — all on Redis
- name: CACHE_STORE
  value: redis
- name: QUEUE_CONNECTION
  value: redis
- name: SESSION_DRIVER
  value: redis
- name: SESSION_LIFETIME
  value: "120"
- name: SESSION_ENCRYPT
  value: "true"
- name: SESSION_SECURE_COOKIE
  value: "true"
- name: BROADCAST_CONNECTION
  value: log
- name: FILESYSTEM_DISK
  value: local

# Mail
- name: MAIL_MAILER
  value: {{ .Values.mail.driver | quote }}
- name: MAIL_FROM_ADDRESS
  value: {{ .Values.mail.fromAddress | quote }}
- name: MAIL_FROM_NAME
  value: {{ .Values.mail.fromName | quote }}
- name: RESEND_KEY
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.name }}
      key: RESEND_KEY
      optional: true

# Octane
- name: OCTANE_SERVER
  value: {{ .Values.web.octaneServer | quote }}
{{- end -}}
