# RÉUSSITE+ — Architecture Complète & Guide Technique
## Plateforme EdTech — République Démocratique du Congo

---

## 1. VISION ARCHITECTURALE

### Philosophie
RÉUSSITE+ est conçu comme une plateforme **JAMstack moderne** avec une approche 
**offline-first** adaptée aux réalités de connexion Internet en RDC.

```
┌─────────────────────────────────────────────────────────┐
│                    RÉUSSITE+ STACK                      │
├──────────────┬──────────────┬──────────────────────────┤
│   FRONTEND   │   BACKEND    │     INFRASTRUCTURE        │
│              │              │                           │
│  Next.js 14  │  Supabase    │  Vercel (CDN mondial)    │
│  (App Router)│  PostgreSQL  │  Cloudflare R2 (PDFs)    │
│  TypeScript  │  Auth        │  Upstash Redis (cache)   │
│  Tailwind    │  Storage     │  Supabase Edge Functions │
│  PWA Ready   │  Realtime    │  Resend (emails)         │
└──────────────┴──────────────┴──────────────────────────┘
```

---

## 2. STRUCTURE DES DOSSIERS NEXT.JS

```
reussiteplus/
├── src/
│   ├── app/                          # App Router (Next.js 14)
│   │   ├── layout.tsx                # Layout racine avec providers
│   │   ├── page.tsx                  # Page d'accueil (SSG + ISR)
│   │   │
│   │   ├── (auth)/                   # Routes authentification
│   │   │   ├── connexion/page.tsx
│   │   │   └── inscription/page.tsx
│   │   │
│   │   ├── (student)/                # Espace étudiant (protégé)
│   │   │   ├── layout.tsx            # Layout avec sidebar
│   │   │   ├── dashboard/page.tsx
│   │   │   ├── archives/
│   │   │   │   ├── page.tsx          # Liste archives (ISR 1h)
│   │   │   │   └── [slug]/page.tsx   # Archive individuelle (SSG)
│   │   │   ├── questions/page.tsx    # Banque questions
│   │   │   ├── examen/
│   │   │   │   ├── page.tsx          # Sélection examen
│   │   │   │   └── [id]/page.tsx     # Session examen live
│   │   │   ├── progression/page.tsx
│   │   │   └── plan-ia/page.tsx
│   │   │
│   │   ├── (admin)/                  # Espace admin (rôle requis)
│   │   │   ├── layout.tsx
│   │   │   ├── admin/page.tsx        # Dashboard admin
│   │   │   ├── admin/archives/page.tsx
│   │   │   ├── admin/questions/page.tsx
│   │   │   ├── admin/users/page.tsx
│   │   │   └── admin/analytics/page.tsx
│   │   │
│   │   └── api/                      # API Routes
│   │       ├── archives/route.ts     # CRUD archives
│   │       ├── questions/route.ts    # CRUD questions
│   │       ├── attempts/route.ts     # Gestion tentatives
│   │       ├── ia/recommend/route.ts # Recommandations IA
│   │       └── search/route.ts       # Recherche unifiée
│   │
│   ├── components/
│   │   ├── ui/                       # Composants de base
│   │   │   ├── Button.tsx
│   │   │   ├── Badge.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Select.tsx
│   │   │   └── Modal.tsx
│   │   │
│   │   ├── archives/
│   │   │   ├── ArchiveBrowser.tsx    # Navigateur archives
│   │   │   ├── ArchiveItem.tsx       # Item archive
│   │   │   ├── ArchiveFilters.tsx    # Filtres
│   │   │   └── PDFViewer.tsx         # Visionneuse PDF inline
│   │   │
│   │   ├── exam/
│   │   │   ├── ExamSession.tsx       # Session d'examen live
│   │   │   ├── Timer.tsx             # Minuteur chronométré
│   │   │   ├── QuestionCard.tsx      # Affichage question
│   │   │   ├── OptionItem.tsx        # Option de réponse QCM
│   │   │   ├── QuestionNav.tsx       # Navigation entre questions
│   │   │   └── Results.tsx           # Page résultats
│   │   │
│   │   ├── questions/
│   │   │   ├── QuestionBank.tsx      # Banque de questions
│   │   │   ├── QuestionFilters.tsx   # Panneau filtres
│   │   │   └── QuestionCard.tsx      # Carte question
│   │   │
│   │   ├── progression/
│   │   │   ├── ScoreChart.tsx        # Graphique progression
│   │   │   ├── StreakCard.tsx         # Carte série jours
│   │   │   └── HistoryTable.tsx       # Historique examens
│   │   │
│   │   ├── ai/
│   │   │   ├── AIRecommendations.tsx  # Panel recommandations
│   │   │   └── RevisionPlan.tsx       # Plan de révision
│   │   │
│   │   └── admin/
│   │       ├── DataTable.tsx          # Tableau données générique
│   │       ├── ImportModal.tsx         # Modal import CSV/PDF
│   │       └── Analytics.tsx           # Dashboard analytics
│   │
│   ├── lib/
│   │   ├── supabase/
│   │   │   ├── client.ts              # Client Supabase browser
│   │   │   ├── server.ts              # Client Supabase serveur
│   │   │   └── types.ts               # Types générés auto
│   │   │
│   │   ├── cache/
│   │   │   ├── redis.ts               # Client Upstash Redis
│   │   │   └── strategies.ts          # Stratégies de cache
│   │   │
│   │   ├── search/
│   │   │   └── engine.ts              # Moteur de recherche
│   │   │
│   │   └── ai/
│   │       ├── recommend.ts           # Algorithme recommandation
│   │       └── plan.ts                # Génération plan révision
│   │
│   ├── hooks/
│   │   ├── useExamSession.ts          # Gestion session examen
│   │   ├── useTimer.ts                # Hook minuteur
│   │   ├── useProgress.ts             # Données progression
│   │   └── useSearch.ts               # Recherche avec debounce
│   │
│   └── stores/
│       ├── examStore.ts               # État Zustand examen
│       └── userStore.ts               # État utilisateur
│
├── public/
│   ├── manifest.json                  # PWA manifest
│   └── sw.js                          # Service Worker
│
└── supabase/
    ├── migrations/                    # Migrations SQL
    ├── functions/                     # Edge Functions
    └── seed.sql                       # Données initiales
```

---

## 3. STRATÉGIE DE CACHE (ANTI-LAG)

### Niveaux de cache (5 couches)

```
REQUÊTE UTILISATEUR
      │
      ▼
┌─────────────────────┐
│  L1: Browser Cache  │  ← localStorage + IndexedDB (offline)
│  PWA Service Worker │    Questions récentes, progression
└──────────┬──────────┘
           │ MISS
           ▼
┌─────────────────────┐
│   L2: CDN Cache     │  ← Vercel Edge Cache
│   (Vercel Edge)     │    Pages statiques, assets
└──────────┬──────────┘
           │ MISS
           ▼
┌─────────────────────┐
│   L3: Redis Cache   │  ← Upstash Redis (TTL configuré)
│   (Upstash)         │    Archives: 2h | Questions: 30min
└──────────┬──────────┘   Stats: 5min | User: 1min
           │ MISS
           ▼
┌─────────────────────┐
│  L4: Supabase DB    │  ← PostgreSQL + Materialized Views
│  (Materialized View)│    mv_archives_stats, mv_classement
└──────────┬──────────┘
           │ MISS
           ▼
┌─────────────────────┐
│  L5: DB Live Query  │  ← Requête PostgreSQL optimisée
└─────────────────────┘
```

### Configuration TTL Redis

```typescript
// lib/cache/strategies.ts

export const CACHE_TTL = {
  // Contenu stable — mis à jour rarement
  ARCHIVES_LIST:    60 * 60 * 2,    // 2 heures
  ARCHIVE_DETAIL:   60 * 60 * 24,   // 24 heures
  
  // Questions — peuvent être ajoutées
  QUESTIONS_LIST:   60 * 30,         // 30 minutes
  QUESTION_DETAIL:  60 * 60,         // 1 heure
  
  // Stats — fréquemment mises à jour
  USER_PROGRESS:    60,              // 1 minute
  GLOBAL_STATS:     60 * 5,          // 5 minutes
  LEADERBOARD:      60 * 10,         // 10 minutes
  
  // Recherche
  SEARCH_RESULTS:   60 * 15,         // 15 minutes
};

// Clés de cache normalisées
export const cacheKey = {
  archives: (type: string, year?: number, matiere?: string) =>
    `archives:${type}:${year ?? 'all'}:${matiere ?? 'all'}`,
  
  question: (id: string) => `question:${id}`,
  
  userProgress: (userId: string) => `user:${userId}:progress`,
  
  search: (query: string, filters: string) =>
    `search:${Buffer.from(query + filters).toString('base64').slice(0, 32)}`,
};
```

---

## 4. API ROUTES CRITIQUES

### 4.1 Archives — Route optimisée avec cache

```typescript
// app/api/archives/route.ts
import { createSupabaseServer } from '@/lib/supabase/server';
import { redis } from '@/lib/cache/redis';
import { CACHE_TTL, cacheKey } from '@/lib/cache/strategies';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(req: NextRequest) {
  const { searchParams } = req.nextUrl;
  const examType = searchParams.get('type') ?? 'all';
  const annee = searchParams.get('annee');
  const matiereId = searchParams.get('matiere');
  const page = parseInt(searchParams.get('page') ?? '1');
  const limit = parseInt(searchParams.get('limit') ?? '20');
  
  // 1. Vérifier le cache Redis
  const key = cacheKey.archives(examType, annee ? parseInt(annee) : undefined, matiereId ?? undefined);
  const cached = await redis.get(key);
  if (cached) {
    return NextResponse.json(JSON.parse(cached as string), {
      headers: {
        'Cache-Control': 'public, s-maxage=3600',
        'X-Cache': 'HIT',
      }
    });
  }
  
  // 2. Requête Supabase optimisée
  const supabase = createSupabaseServer();
  let query = supabase
    .from('archives')
    .select(`
      id, titre, slug, exam_type, annee, session,
      sujet_url, corrige_url, vues, telechargements,
      matieres!inner(id, nom, code, couleur),
      provinces(id, nom, code)
    `, { count: 'exact' })
    .eq('status', 'PUBLIE')
    .order('annee', { ascending: false })
    .order('created_at', { ascending: false })
    .range((page - 1) * limit, page * limit - 1);
  
  // Filtres dynamiques
  if (examType !== 'all') query = query.eq('exam_type', examType);
  if (annee) query = query.eq('annee', parseInt(annee));
  if (matiereId) query = query.eq('matiere_id', matiereId);
  
  const { data, error, count } = await query;
  
  if (error) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
  
  const response = {
    data,
    pagination: {
      total: count ?? 0,
      page,
      limit,
      pages: Math.ceil((count ?? 0) / limit),
    }
  };
  
  // 3. Stocker en cache Redis
  await redis.setex(key, CACHE_TTL.ARCHIVES_LIST, JSON.stringify(response));
  
  return NextResponse.json(response, {
    headers: {
      'Cache-Control': 'public, s-maxage=3600',
      'X-Cache': 'MISS',
    }
  });
}
```

### 4.2 Moteur de Recherche Full-Text

```typescript
// app/api/search/route.ts
export async function GET(req: NextRequest) {
  const query = req.nextUrl.searchParams.get('q') ?? '';
  const type = req.nextUrl.searchParams.get('type') ?? 'all';
  
  if (query.length < 2) {
    return NextResponse.json({ results: [] });
  }
  
  const supabase = createSupabaseServer();
  
  // Recherche parallèle dans archives ET questions
  const [archivesResult, questionsResult] = await Promise.all([
    // Recherche archives avec full-text PostgreSQL
    supabase.rpc('search_archives', {
      p_query: query,
      p_exam_type: type !== 'all' ? type : null,
      p_limit: 10,
    }),
    
    // Recherche questions
    supabase.rpc('search_questions', {
      p_query: query,
      p_limit: 10,
    }),
  ]);
  
  return NextResponse.json({
    archives: archivesResult.data ?? [],
    questions: questionsResult.data ?? [],
    total: (archivesResult.data?.length ?? 0) + (questionsResult.data?.length ?? 0),
  });
}
```

### 4.3 Recommandations IA

```typescript
// app/api/ia/recommend/route.ts
// Algorithme de recommandation basé sur les statistiques utilisateur

export async function GET(req: NextRequest) {
  const userId = req.headers.get('x-user-id');
  if (!userId) return NextResponse.json({ error: 'Non authentifié' }, { status: 401 });
  
  const supabase = createSupabaseServer();
  
  // 1. Récupérer les statistiques utilisateur par chapitre
  const { data: stats } = await supabase
    .from('attempt_responses')
    .select(`
      est_correcte, temps_secondes,
      question_bank!inner(
        chapitre_id, matiere_id,
        chapitres(id, titre),
        matieres(id, nom)
      ),
      attempts!inner(user_id, completed_at)
    `)
    .eq('attempts.user_id', userId)
    .gte('attempts.completed_at', new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString())
    .not('question_bank.chapitre_id', 'is', null);
  
  // 2. Calculer les taux par chapitre
  const chapitreStats: Record<string, { correct: number; total: number; nom: string; matiere: string }> = {};
  
  for (const response of stats ?? []) {
    const chapId = response.question_bank.chapitre_id;
    if (!chapId) continue;
    
    if (!chapitreStats[chapId]) {
      chapitreStats[chapId] = {
        correct: 0,
        total: 0,
        nom: response.question_bank.chapitres?.titre ?? 'Inconnu',
        matiere: response.question_bank.matieres?.nom ?? 'Inconnu',
      };
    }
    
    chapitreStats[chapId].total++;
    if (response.est_correcte) chapitreStats[chapId].correct++;
  }
  
  // 3. Identifier les faiblesses (taux < 60%, minimum 3 questions)
  const faiblesses = Object.entries(chapitreStats)
    .filter(([_, s]) => s.total >= 3 && (s.correct / s.total) < 0.60)
    .map(([id, s]) => ({
      chapitre_id: id,
      nom: s.nom,
      matiere: s.matiere,
      taux: Math.round((s.correct / s.total) * 100),
      priorite: (s.correct / s.total) < 0.30 ? 'urgente' : 
                (s.correct / s.total) < 0.45 ? 'haute' : 'normale',
      nb_questions_disponibles: 0, // À remplir
    }))
    .sort((a, b) => a.taux - b.taux)
    .slice(0, 8);
  
  // 4. Générer le plan de révision
  const plan = {
    analyse_date: new Date().toISOString(),
    score_global: calculateGlobalScore(chapitreStats),
    faiblesses,
    recommandations: generateRecommendations(faiblesses),
    prochaine_revision: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
  };
  
  // 5. Mettre à jour le cache utilisateur
  await supabase.rpc('update_user_ia_plan', { p_user_id: userId, p_plan: plan });
  
  return NextResponse.json(plan);
}

function calculateGlobalScore(stats: Record<string, any>): number {
  const values = Object.values(stats);
  if (!values.length) return 0;
  const total = values.reduce((s: number, v: any) => s + v.total, 0);
  const correct = values.reduce((s: number, v: any) => s + v.correct, 0);
  return Math.round((correct / total) * 100);
}

function generateRecommendations(faiblesses: any[]): string[] {
  const recs = [];
  const urgentes = faiblesses.filter(f => f.priorite === 'urgente');
  
  if (urgentes.length) {
    recs.push(`Concentrez-vous en priorité sur ${urgentes.map(f => f.nom).join(', ')}`);
  }
  
  recs.push('Faites 15 minutes de révision ciblée par jour sur vos chapitres faibles');
  recs.push('Passez un examen blanc complet chaque semaine pour suivre votre progression');
  
  return recs;
}
```

---

## 5. OPTIMISATION PERFORMANCE NEXT.JS

### 5.1 Stratégies de rendu (SSR/SSG/ISR)

```typescript
// app/archives/page.tsx — ISR (mise à jour toutes les heures)
export const revalidate = 3600; // 1 heure

// app/archives/[slug]/page.tsx — SSG + régénération
export async function generateStaticParams() {
  // Pré-générer les 200 archives les plus vues
  const supabase = createSupabaseServer();
  const { data } = await supabase
    .from('archives')
    .select('slug')
    .eq('status', 'PUBLIE')
    .order('vues', { ascending: false })
    .limit(200);
  
  return (data ?? []).map(a => ({ slug: a.slug }));
}

export const revalidate = 86400; // Re-générer toutes les 24h

// app/dashboard/page.tsx — SSR (données personnalisées)
// Pas de revalidate = SSR à chaque requête
```

### 5.2 Optimisation des images et PDF

```typescript
// Configuration Next.js pour les PDFs Supabase Storage
// next.config.js
module.exports = {
  images: {
    domains: ['YOUR_PROJECT.supabase.co'],
    formats: ['image/avif', 'image/webp'],
  },
  
  // Compression Gzip/Brotli
  compress: true,
  
  // Optimisations supplémentaires
  experimental: {
    optimizeCss: true,
    scrollRestoration: true,
  },
  
  // Headers de cache CDN
  async headers() {
    return [
      {
        source: '/archives/:path*',
        headers: [
          { key: 'Cache-Control', value: 'public, max-age=3600, s-maxage=86400' },
        ],
      },
    ];
  },
};
```

### 5.3 Code Splitting et Lazy Loading

```typescript
// Lazy loading des composants lourds
import dynamic from 'next/dynamic';

// PDFViewer chargé seulement quand nécessaire (lourd: ~200KB)
const PDFViewer = dynamic(() => import('@/components/archives/PDFViewer'), {
  loading: () => <div className="animate-pulse h-96 bg-gray-100 rounded-xl"/>,
  ssr: false, // Côté client uniquement
});

// ExamSession chargé seulement sur la page examen
const ExamSession = dynamic(() => import('@/components/exam/ExamSession'), {
  ssr: false,
});
```

---

## 6. URLS SEO OPTIMISÉES

### Structure des URLs (conformément aux specs)

```
/archives/enafep/2024/mathematiques          → ENAFEP 2024 Maths
/archives/enafep/2024/mathematiques/corrige  → Corrigé
/archives/tenasosp/2023/francais             → TENASOSP 2023 Français
/archives/examen-etat/2022/sciences          → État 2022 Sciences
/archives/diocesain/2024/kinshasa/maths      → Diocésain Kinshasa

/questions/enafep/mathematiques/geometrie    → Questions par chapitre
/examens/blanc/enafep-2024-maths             → Examen blanc

/blog/preparation-enafep-2025                → Contenu SEO
/blog/programme-examen-etat-rdc              → Contenu SEO
```

### Metadata SEO dynamique

```typescript
// app/archives/[slug]/page.tsx
export async function generateMetadata({ params }: { params: { slug: string } }) {
  const archive = await getArchiveBySlug(params.slug);
  
  return {
    title: `${archive.titre} | RÉUSSITE+ Archives RDC`,
    description: `Téléchargez gratuitement ${archive.titre}. Sujet officiel et corrigé pour préparer ${archive.exam_type} en République Démocratique du Congo.`,
    openGraph: {
      title: archive.titre,
      description: `Archive officielle ${archive.exam_type} ${archive.annee}`,
      url: `https://reussiteplus.cd/archives/${params.slug}`,
      type: 'article',
    },
    alternates: {
      canonical: `https://reussiteplus.cd/archives/${params.slug}`,
    },
  };
}
```

---

## 7. SUPABASE EDGE FUNCTIONS

### 7.1 Génération automatique d'examens

```typescript
// supabase/functions/generate-exam/index.ts
import { serve } from 'https://deno.land/std@0.168.0/http/server.ts';
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

serve(async (req) => {
  const { userId, config } = await req.json();
  // config: { exam_type, matieres, nb_questions, difficulte, annees }
  
  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
  );
  
  // 1. Récupérer les questions déjà vues par l'utilisateur
  const { data: history } = await supabase
    .from('question_history')
    .select('question_id')
    .eq('user_id', userId)
    .eq('maitrisee', true);
  
  const seenIds = (history ?? []).map(h => h.question_id);
  
  // 2. Répartir les questions par matière
  const questionsParMatiere = Math.floor(config.nb_questions / config.matieres.length);
  const allQuestions: any[] = [];
  
  for (const matiereId of config.matieres) {
    let query = supabase
      .from('question_bank')
      .select('id, enonce, type, difficulte, points, temps_suggere, matiere_id')
      .eq('status', 'PUBLIE')
      .eq('matiere_id', matiereId)
      .not('id', 'in', `(${seenIds.join(',')})`)
      .order('RANDOM()')
      .limit(questionsParMatiere);
    
    if (config.difficulte) query = query.eq('difficulte', config.difficulte);
    if (config.exam_type) query = query.eq('exam_type', config.exam_type);
    
    const { data } = await query;
    allQuestions.push(...(data ?? []));
  }
  
  // 3. Créer l'examen blanc
  const { data: examen } = await supabase
    .from('examens_blancs')
    .insert({
      titre: `Examen IA personnalisé — ${new Date().toLocaleDateString('fr-FR')}`,
      exam_type: config.exam_type,
      nb_questions: allQuestions.length,
      duree_minutes: allQuestions.reduce((s, q) => s + (q.temps_suggere ?? 90), 0) / 60,
      est_aleatoire: true,
      est_personnalise: true,
      created_by: userId,
    })
    .select()
    .single();
  
  // 4. Lier les questions à l'examen
  await supabase.from('examen_questions').insert(
    allQuestions.map((q, i) => ({
      examen_id: examen.id,
      question_id: q.id,
      ordre: i + 1,
      points: q.points,
    }))
  );
  
  return new Response(JSON.stringify({ examen_id: examen.id }), {
    headers: { 'Content-Type': 'application/json' },
  });
});
```

---

## 8. PWA & OFFLINE SUPPORT

### Service Worker pour fonctionnement hors ligne

```javascript
// public/sw.js — Service Worker
const CACHE_NAME = 'reussiteplus-v1';
const STATIC_ASSETS = [
  '/',
  '/dashboard',
  '/archives',
  '/offline.html',
];

// Cache des ressources statiques à l'installation
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
});

// Stratégie: Network First, puis Cache (fraîcheur prioritaire)
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  
  // API: Network only (données dynamiques)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => 
        new Response(JSON.stringify({ error: 'Hors ligne' }), {
          headers: { 'Content-Type': 'application/json' }
        })
      )
    );
    return;
  }
  
  // PDFs Archives: Cache First (économiser la bande passante)
  if (url.pathname.includes('.pdf') || url.hostname.includes('supabase')) {
    event.respondWith(
      caches.match(event.request).then(cached => 
        cached ?? fetch(event.request).then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          return response;
        })
      )
    );
    return;
  }
  
  // Pages: Stale While Revalidate
  event.respondWith(
    caches.match(event.request).then(cached => {
      const networkFetch = fetch(event.request).then(response => {
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, response.clone()));
        return response;
      });
      return cached ?? networkFetch;
    })
  );
});
```

---

## 9. SCALABILITÉ — 100K+ UTILISATEURS

### Architecture de montée en charge

```
TRAFIC NORMAL (< 1000 utilisateurs simultanés)
┌──────────────────────────────────────────┐
│  Vercel Free/Pro → Supabase Pro          │
│  Redis: Upstash 10k requêtes/jour        │
│  Coût estimé: ~50$/mois                  │
└──────────────────────────────────────────┘

PICS D'EXAMENS (10k utilisateurs simultanés)
┌──────────────────────────────────────────┐
│  Vercel Enterprise + Edge Functions      │
│  Supabase Pro (Connection Pooling PgBouncer)│
│  Redis: Upstash Pay-as-you-go            │
│  CDN: Cloudflare pour PDFs               │
│  Coût estimé: ~200$/mois                 │
└──────────────────────────────────────────┘

SCALE NATIONAL (100k+ utilisateurs)
┌──────────────────────────────────────────┐
│  Vercel Enterprise                       │
│  Supabase (Read Replicas)                │
│  Redis Cluster                           │
│  Cloudflare R2 pour Storage              │
│  Coût estimé: ~800$/mois                 │
└──────────────────────────────────────────┘
```

### Connection Pooling pour pics d'examens

```sql
-- supabase/migrations/connection_pooling.sql
-- Configurer PgBouncer dans Supabase Dashboard

-- Index pour réduire la charge CPU des requêtes concurrentes
-- (déjà dans le schema principal, rappel ici)
-- Ces indexes réduisent le temps de requête de 100ms → 2ms

CREATE INDEX CONCURRENTLY idx_attempts_live
  ON attempts(user_id, status)
  WHERE status = 'en_cours';

-- Vue matérialisée rafraîchie toutes les 5 minutes via pg_cron
SELECT cron.schedule(
  'refresh-archives-stats',
  '*/60 * * * *',  -- Toutes les heures
  'REFRESH MATERIALIZED VIEW CONCURRENTLY mv_archives_stats'
);

SELECT cron.schedule(
  'refresh-classement',
  '*/10 * * * *',  -- Toutes les 10 minutes
  'REFRESH MATERIALIZED VIEW CONCURRENTLY mv_classement'
);
```

---

## 10. PAIEMENTS MOBILE MONEY RDC

```typescript
// lib/payments/mobileMoney.ts
// Intégration M-Pesa / Airtel Money / Orange Money

export interface PaymentRequest {
  userId: string;
  plan: 'BASIQUE' | 'PREMIUM';
  provider: 'MPESA' | 'AIRTEL_MONEY' | 'ORANGE_MONEY';
  phoneNumber: string; // Format: +243XXXXXXXXX
}

// Prix en Francs Congolais (CDF)
export const PRICES = {
  BASIQUE: {
    monthly: 5000,    // ~2.5 USD
    annual: 50000,    // ~25 USD (save 2 months)
  },
  PREMIUM: {
    monthly: 10000,   // ~5 USD
    annual: 100000,   // ~50 USD
  },
  ECOLE: {
    monthly: 50000,   // ~25 USD (illimité pour l'école)
    annual: 500000,
  },
};
```

---

## 11. CHECKLIST DÉPLOIEMENT PRODUCTION

### Variables d'environnement requises

```bash
# .env.local / Variables Vercel

# Supabase
NEXT_PUBLIC_SUPABASE_URL=https://xxxxx.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=eyJhbGci...
SUPABASE_SERVICE_ROLE_KEY=eyJhbGci...

# Redis (Upstash)
UPSTASH_REDIS_REST_URL=https://xxx.upstash.io
UPSTASH_REDIS_REST_TOKEN=AXxx...

# Emails (Resend)
RESEND_API_KEY=re_xxx...
EMAIL_FROM=noreply@reussiteplus.cd

# App
NEXT_PUBLIC_APP_URL=https://reussiteplus.cd
NEXT_PUBLIC_UPLOAD_MAX_SIZE_MB=50
```

### Checklist avant mise en production

- [ ] Migrations SQL appliquées (schema.sql)
- [ ] Row Level Security activé sur toutes les tables sensibles
- [ ] Variables d'environnement configurées sur Vercel
- [ ] Domaine reussiteplus.cd configuré
- [ ] CDN Cloudflare configuré pour les PDFs
- [ ] Vues matérialisées créées et indexées
- [ ] CRON jobs configurés (refresh vues matérialisées)
- [ ] Monitoring Sentry configuré
- [ ] Analytics (Plausible/Posthog) configurés
- [ ] Backup automatique Supabase activé
- [ ] Tests de charge avec k6 (simuler 10k utilisateurs)
- [ ] Lighthouse score > 90 sur mobile

---

*RÉUSSITE+ — Construisons l'avenir éducatif de la RDC* 🇨🇩
