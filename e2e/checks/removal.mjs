import { INSTALL_CHARACTERS, NAME, STRANGER_CHARACTERS, expectJson, expectStatus, loadFixture } from "../support.mjs";

export async function removalAnswersAndEmptiesStatus(ctx) {
  const answer = expectStatus(await ctx.client.remove(), 200, "DELETE /api/v1/upload");
  const removed = expectJson(answer, "DELETE /api/v1/upload");
  const parts = INSTALL_CHARACTERS.reduce((sum, name) => sum + loadFixture(name).ids.length, 0);
  if (removed.characters_removed !== INSTALL_CHARACTERS.length || removed.parts_removed !== parts || typeof removed.rows_removed !== "number") {
    throw new Error(`the removal should report ${INSTALL_CHARACTERS.length} characters and ${parts} parts, saw ${answer.text.slice(0, 300)}`);
  }
  const status = expectStatus(await ctx.client.status(), 200, "GET /api/v1/upload/status after the removal");
  const left = expectJson(status, "the status after the removal").characters ?? [];
  if (left.length !== 0) throw new Error(`the status should list no characters after the removal, saw ${status.text.slice(0, 300)}`);
}

export async function strangerIsRemoved(ctx) {
  const answer = expectStatus(await ctx.stranger.remove(), 200, "DELETE /api/v1/upload of the stranger install");
  const removed = expectJson(answer, "DELETE /api/v1/upload of the stranger install");
  const parts = STRANGER_CHARACTERS.reduce((sum, name) => sum + loadFixture(name).ids.length, 0);
  if (removed.characters_removed !== STRANGER_CHARACTERS.length || removed.parts_removed !== parts) {
    throw new Error(`the removal of the stranger install should report ${STRANGER_CHARACTERS.length} characters and ${parts} parts, saw ${answer.text.slice(0, 300)}`);
  }
}

export async function characterIsNoLongerServed(ctx) {
  const page = await ctx.http.page(`/c/${NAME}`);
  if (page.status !== 404) throw new Error(`/c/${NAME} should be a 404 page once the upload is removed, saw ${page.status}: ${page.text.slice(0, 300)}`);
  const answer = await ctx.http.api(`/api/v1/public/characters/${NAME}?limit=1`);
  if (answer.status !== 404) throw new Error(`GET /api/v1/public/characters/${NAME} should be 404 once the upload is removed, saw ${answer.status}: ${answer.text.slice(0, 300)}`);
}
