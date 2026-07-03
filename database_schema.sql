-- Supabase Database Schema Additions for Vercel Migration

-- 1. Create the sessions table for stateless session management
CREATE TABLE IF NOT EXISTS public.sessions (
    id TEXT PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL
);

-- Index to quickly clean up expired sessions
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON public.sessions (expires_at);

-- 2. Storage Bucket setup (Note: In Supabase, you typically do this via the Dashboard, but here is the SQL equivalent if needed)
-- Ensure the storage schema exists (it usually does by default on Supabase)
-- CREATE SCHEMA IF NOT EXISTS storage;

-- Insert the assignments bucket
INSERT INTO storage.buckets (id, name, public) 
VALUES ('assignments', 'assignments', true)
ON CONFLICT (id) DO NOTHING;

-- Allow public read access to the bucket
CREATE POLICY "Public Access" 
ON storage.objects FOR SELECT 
USING ( bucket_id = 'assignments' );

-- Allow authenticated/service-role uploads (Supabase handles this with the service key)
