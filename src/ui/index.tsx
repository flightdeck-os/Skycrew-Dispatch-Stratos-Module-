import { useEffect, useMemo, useState } from "react";
import { usePluginContext, useVaApi } from "@skyvexsoftware/stratos-sdk";
import { Plane, RefreshCcw, ClipboardList, Package, Users, MapPin, AlertCircle, CheckCircle2 } from "lucide-react";
import "./styles.css";

type Flight = {
  id?: number;
  bid_id?: number;
  number?: string;
  flight_number?: string;
  code?: string;
  departure_airport?: string;
  arrival_airport?: string;
  distance?: number;
  flight_time?: number;
  type?: string;
  aircraft?: number | string | null;
  aircraft_details?: {
    id?: number;
    code?: string;
    name?: string;
    registration?: string;
    maximum_passengers?: number;
    maximum_cargo?: number;
  } | null;
  notes?: string;
};

type DispatchBriefing = {
  ok?: boolean;
  flight?: Flight;
  booking?: Record<string, unknown> | null;
  aircraft?: Record<string, unknown> | null;
  route?: Record<string, unknown> | null;
  manifest?: Array<Record<string, unknown>>;
  cargo_manifest?: Array<Record<string, unknown>>;
  warnings?: string[];
  dispatch?: {
    status?: string;
    release_number?: string;
    generated_at?: string;
    remarks?: string;
  };
};

function StatCard({ label, value, icon: Icon }: { label: string; value: string | number; icon: any }) {
  return (
    <div className="rounded-2xl border bg-card p-4 shadow-sm">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-foreground">{value}</p>
        </div>
        <div className="rounded-xl bg-primary/10 p-3 text-primary">
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

function valueOrDash(value: unknown) {
  if (value === null || value === undefined || value === "") return "—";
  return String(value);
}

export default function SkyCrewDispatchPlugin() {
  const { auth, airline, toast } = usePluginContext();
  const va = useVaApi();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [bookings, setBookings] = useState<Flight[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [briefing, setBriefing] = useState<DispatchBriefing | null>(null);

  const selectedFlight = useMemo(() => {
    return bookings.find((f) => Number(f.bid_id ?? f.id) === selectedId) ?? bookings[0] ?? null;
  }, [bookings, selectedId]);

  async function loadDispatch() {
    setLoading(true);
    setError(null);

    try {
      const bookingsRes = await va.get("/flights/bookings");
      const bookingList: Flight[] = Array.isArray(bookingsRes.data) ? bookingsRes.data : bookingsRes.data?.data ?? [];
      setBookings(bookingList);

      const first = selectedFlight ?? bookingList[0];
      const id = first ? Number(first.bid_id ?? first.id) : null;

      if (id) {
        setSelectedId(id);
        const briefRes = await va.get(`/dispatch/briefing?booking_id=${id}`);
        setBriefing(briefRes.data);
      } else {
        setBriefing(null);
      }
    } catch (e: any) {
      const message = e?.response?.data?.error || e?.message || "Unable to load dispatch data.";
      setError(message);
      toast?.error?.(message);
    } finally {
      setLoading(false);
    }
  }

  async function selectFlight(flight: Flight) {
    const id = Number(flight.bid_id ?? flight.id);
    setSelectedId(id);
    setLoading(true);
    setError(null);

    try {
      const briefRes = await va.get(`/dispatch/briefing?booking_id=${id}`);
      setBriefing(briefRes.data);
    } catch (e: any) {
      const message = e?.response?.data?.error || e?.message || "Unable to load selected dispatch briefing.";
      setError(message);
      toast?.error?.(message);
    } finally {
      setLoading(false);
    }
  }

  async function releaseDispatch() {
    if (!selectedFlight) return;
    const id = Number(selectedFlight.bid_id ?? selectedFlight.id);
    setLoading(true);

    try {
      const res = await va.post("/dispatch/release", { booking_id: id });
      setBriefing(res.data);
      toast?.success?.("Dispatch released from SkyCrew.");
    } catch (e: any) {
      const message = e?.response?.data?.error || e?.message || "Unable to release dispatch.";
      toast?.error?.(message);
      setError(message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (auth.isAuthenticated) {
      loadDispatch();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [auth.isAuthenticated]);

  if (!auth.isAuthenticated || !auth.user) {
    return (
      <div className="flex h-full items-center justify-center p-8">
        <div className="rounded-2xl border bg-card p-6 text-center shadow-sm">
          <AlertCircle className="mx-auto h-8 w-8 text-muted-foreground" />
          <h2 className="mt-3 text-lg font-semibold">Login required</h2>
          <p className="mt-2 text-sm text-muted-foreground">Please log into your SkyCrew airline connection before using Dispatch.</p>
        </div>
      </div>
    );
  }

  const active = selectedFlight;
  const manifest = briefing?.cargo_manifest?.length ? briefing.cargo_manifest : briefing?.manifest ?? [];
  const isCargo = active?.type === "C" || Boolean(briefing?.cargo_manifest?.length);

  return (
    <div className="min-h-full bg-background p-6 text-foreground">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-primary">FlightDeck OS SkyCrew</p>
          <h1 className="mt-2 text-3xl font-bold">Dispatch Centre</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Connected to {airline?.name ?? "your VA"}. Review booked flights, dispatch release, aircraft, route, and manifest information.
          </p>
        </div>
        <button
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm disabled:opacity-60"
          onClick={loadDispatch}
          disabled={loading}
        >
          <RefreshCcw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Refresh Dispatch
        </button>
      </div>

      {error && (
        <div className="mb-4 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <StatCard label="Booked Flights" value={bookings.length} icon={ClipboardList} />
        <StatCard label="Selected Type" value={isCargo ? "Cargo" : "Passenger"} icon={isCargo ? Package : Users} />
        <StatCard label="Departure" value={valueOrDash(active?.departure_airport)} icon={MapPin} />
        <StatCard label="Arrival" value={valueOrDash(active?.arrival_airport)} icon={Plane} />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[360px_1fr]">
        <div className="rounded-2xl border bg-card shadow-sm">
          <div className="border-b p-4">
            <h2 className="text-lg font-semibold">Booked Flights</h2>
            <p className="text-sm text-muted-foreground">Flights returned from SkyCrew Dispatch.</p>
          </div>
          <div className="max-h-[620px] divide-y overflow-auto">
            {bookings.length === 0 ? (
              <div className="p-4 text-sm text-muted-foreground">No booked flights found.</div>
            ) : (
              bookings.map((flight) => {
                const id = Number(flight.bid_id ?? flight.id);
                const selected = id === Number(active?.bid_id ?? active?.id);
                return (
                  <button
                    key={`${flight.number}-${id}`}
                    onClick={() => selectFlight(flight)}
                    className={`w-full p-4 text-left transition hover:bg-muted ${selected ? "bg-primary/10" : ""}`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-semibold">{flight.code ?? ""}{flight.number ?? flight.flight_number}</span>
                      <span className="rounded-full border px-2 py-0.5 text-xs text-muted-foreground">{flight.type === "C" ? "Cargo" : "Passenger"}</span>
                    </div>
                    <div className="mt-2 text-sm text-muted-foreground">
                      {valueOrDash(flight.departure_airport)} → {valueOrDash(flight.arrival_airport)}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                      {flight.aircraft_details?.registration ?? "No aircraft"} · {flight.aircraft_details?.code ?? "—"}
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        <div className="space-y-6">
          <div className="rounded-2xl border bg-card shadow-sm">
            <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <h2 className="text-lg font-semibold">Dispatch Briefing</h2>
                <p className="text-sm text-muted-foreground">Briefing data pulled from your SkyCrew dispatch module.</p>
              </div>
              <button
                onClick={releaseDispatch}
                disabled={!active || loading}
                className="inline-flex items-center justify-center gap-2 rounded-xl border bg-background px-4 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-60"
              >
                <CheckCircle2 className="h-4 w-4" />
                Release Dispatch
              </button>
            </div>

            <div className="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3">
              <div className="rounded-xl border bg-background p-4">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Flight</p>
                <p className="mt-1 text-xl font-semibold">{active?.code ?? ""}{active?.number ?? active?.flight_number ?? "—"}</p>
                <p className="mt-1 text-sm text-muted-foreground">{valueOrDash(active?.departure_airport)} → {valueOrDash(active?.arrival_airport)}</p>
              </div>
              <div className="rounded-xl border bg-background p-4">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Aircraft</p>
                <p className="mt-1 text-xl font-semibold">{active?.aircraft_details?.registration ?? "—"}</p>
                <p className="mt-1 text-sm text-muted-foreground">{active?.aircraft_details?.name ?? active?.aircraft_details?.code ?? "No aircraft details"}</p>
              </div>
              <div className="rounded-xl border bg-background p-4">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Dispatch</p>
                <p className="mt-1 text-xl font-semibold">{briefing?.dispatch?.status ?? "Ready"}</p>
                <p className="mt-1 text-sm text-muted-foreground">Release: {briefing?.dispatch?.release_number ?? "Not issued"}</p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="rounded-2xl border bg-card p-4 shadow-sm">
              <h3 className="font-semibold">Manifest</h3>
              <p className="mb-3 text-sm text-muted-foreground">{isCargo ? "Cargo loads" : "Passenger load"} from SkyCrew.</p>
              <div className="space-y-2">
                {manifest.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No manifest has been generated for this dispatch yet.</p>
                ) : (
                  manifest.slice(0, 12).map((item, idx) => (
                    <div key={idx} className="rounded-xl border bg-background p-3 text-sm">
                      <div className="font-medium">{valueOrDash(item.type ?? item.name ?? item.description ?? `Item ${idx + 1}`)}</div>
                      <div className="text-muted-foreground">
                        Weight: {valueOrDash(item.weight_lbs ?? item.weight ?? item.cargo_weight_lbs)} lbs · Position: {valueOrDash(item.position ?? item.seat ?? item.slot)}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            <div className="rounded-2xl border bg-card p-4 shadow-sm">
              <h3 className="font-semibold">Warnings & Notes</h3>
              <p className="mb-3 text-sm text-muted-foreground">Dispatch validation from SkyCrew.</p>
              <div className="space-y-2">
                {(briefing?.warnings ?? []).length === 0 ? (
                  <div className="rounded-xl border bg-background p-3 text-sm text-muted-foreground">No current dispatch warnings.</div>
                ) : (
                  briefing?.warnings?.map((warning, idx) => (
                    <div key={idx} className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300">
                      {warning}
                    </div>
                  ))
                )}
                {briefing?.dispatch?.remarks && (
                  <div className="rounded-xl border bg-background p-3 text-sm text-muted-foreground">{briefing.dispatch.remarks}</div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
