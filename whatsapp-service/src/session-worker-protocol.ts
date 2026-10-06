export type WorkerAction =
  | 'initialize'
  | 'restart'
  | 'logout'
  | 'remove'
  | 'heartbeat'
  | 'send'
  | 'sendCampaign'
  | 'destroy';

export type WorkerRequest = {
  kind: 'request';
  id: string;
  action: WorkerAction;
  payload?: unknown;
};

export type WorkerResponse = {
  kind: 'response';
  id: string;
  ok: boolean;
  result?: unknown;
  error?: string;
};

export type WorkerEvent = {
  kind: 'event';
  event: 'status' | 'campaign_ack';
  payload: Record<string, unknown>;
};

export type WorkerMessage = WorkerResponse | WorkerEvent;
